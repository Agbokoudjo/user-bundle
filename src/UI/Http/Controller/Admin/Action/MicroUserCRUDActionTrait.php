<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Phone: +229 01 67 25 18 86
 * LinkedIn: https://www.linkedin.com/in/internationales-web-apps-services-120520193/
 * Github: https://github.com/Agbokoudjo/
 * Company: INTERNATIONALES WEB APPS & SERVICES
 *
 * For more information, please feel free to contact the author.
 */

namespace Wlindabla\UserBundle\UI\Http\Controller\Admin\Action;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface as SymfonySecurityUserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeRequestServiceInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\Persistance\EmailChangeRequestRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\GenerateTemporaryPasswordHandler;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\ToggleUserAccountHandler;
use Wlindabla\UserBundle\Domain\User\Application\Dto\SubmittedUserDto;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Enum\AccountStatus;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Exception\TokenGenerationCooldownException;
use Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Wlindabla\UserBundle\Infrastructure\Service\UserDuplicateFinderService;
use Wlindabla\UserBundle\UI\Http\Controller\Trait\ResolvesManagedCurrentUserTrait;

/**
 * Ready-to-use account-management actions (resend verification, toggle
 * enabled/disabled, regenerate a temporary password) for a user CRUD
 * controller — independent of any specific admin framework.
 *
 * i18n: exception messages are English-only (logs / debugging). Every
 * `title` and `message` sent back to the frontend is translated through
 * the injected TranslatorInterface, domain `WlindablaUserBundle`
 * (keys under `user_action.*`, see translations/WlindablaUserBundle.{en,fr}.yaml).
 *
 * Usage with SonataAdmin: `use MicroUserCRUDActionTrait;` in your own CRUD
 * controller extending `Sonata\AdminBundle\Controller\CRUDController`, and
 * register the routes yourself in `configureRoutes()`:
 *
 *   $collection->add('toggle_enabled_user_account', 'toggle_enabled_user_account/{id}', [
 *       '_controller' => self::class.'::toggleEnabledUserAccountAction',
 *       '_sonata_admin' => $this->getCode(),
 *   ], methods: ['PATCH']);
 *
 * Usage with another tool (EasyAdmin, a hand-rolled controller, …): same
 * principle — this trait only provides the actions, routing/wiring is the
 * consuming application's responsibility.
 *
 * Authorization is NOT enforced by this trait — it is framework-specific
 * (Sonata's own checkAccess(), a Symfony voter, …) and must be added by
 * the consuming controller.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
trait MicroUserCRUDActionTrait
{
    use ResolvesManagedCurrentUserTrait;
    
    public function resendEmailVerifyAction(
        Request $request,
        string|int $id,
        EmailVerificationInterface $verificationService,
        UserManagerInterface $userManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->_isXmlHttpRequest($request) || !$request->isMethod('GET')) {
            throw new BadRequestHttpException('Unauthorized request. Please use the appropriate GET/AJAX channel.');
        }

        if ('' === trim((string) $id)) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'danger',
                titleKey: 'user_action.common.missing_data_title',
                messageKey: 'user_action.common.id_required',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        /** @var UserInterface|null $user */
        $user = $userManager->find($id);

        if (!$user instanceof UserInterface) {
            return $this->createUserNotFoundResponse($translator, $id);
        }

        if ($user->isEmailVerified()) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'warning',
                titleKey: 'user_action.resend_verification.already_verified_title',
                messageKey: 'user_action.resend_verification.already_verified',
                messageParameters: ['%email%' => $user->getEmail(), '%username%' => $user->getUsername()],
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        try {
            $verificationService->resendVerificationEmail($id);
        } catch (TokenGenerationCooldownException $e) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'warning',
                titleKey: 'user_action.resend_verification.failed_title',
                messageKey: $e->getTranslationKey(),
                messageParameters: $e->getTranslationParameters(),
                statusCode: Response::HTTP_TOO_MANY_REQUESTS,
            );
        } catch (InvalidTokenException $e) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'warning',
                titleKey: 'user_action.resend_verification.failed_title',
                messageKey: $e->getTranslationKey(),
                messageParameters: $e->getTranslationParameters(),
                statusCode: $e->isUserNotFound() ? Response::HTTP_NOT_FOUND : Response::HTTP_BAD_REQUEST,
            );
        } catch (\Throwable) {
            // Never leak $e->getMessage() to the frontend: it is an
            // English technical message, not a translated one.
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'danger',
                titleKey: 'user_action.resend_verification.failed_title',
                messageKey: 'user_action.resend_verification.failed',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        return $this->createTranslatedFlashJsonResponse(
            $translator,
            type: 'success',
            titleKey: 'user_action.resend_verification.success_title',
            messageKey: 'user_action.resend_verification.success',
            statusCode: Response::HTTP_OK,
        );
    }

    /**
     * Activates or deactivates a user account. The actual status change
     * happens asynchronously, in ToggleUserAccountHandler — not here.
     *
     * @throws BadRequestHttpException if the request isn't valid
     */
    public function toggleEnabledUserAccountAction(
        Request $request,
        string|int $id,
        #[CurrentUser] SymfonySecurityUserInterface $currentUserConnected,
        UserManagerInterface $userManager,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->_isXmlHttpRequest($request) || !$request->isMethod('PATCH')) {
            throw new BadRequestHttpException('Unauthorized request. Please use the appropriate PATCH/AJAX channel.');
        }

        try {
            if ('' === trim((string) $id)) {
                throw new BadRequestHttpException('The user identifier is missing.');
            }

            /** @var UserInterface|null $user */
            $user = $userManager->find($id);

            if (!$user instanceof UserInterface) {
                return $this->createUserNotFoundResponse($translator, $id);
            }

            if (!$user->isEmailVerified()) {
                return $this->createEmailNotVerifiedResponse($translator, $user, 'danger');
            }

            $isEnabled = $this->getStatusFromRequest($request);
            $accountStatus = $isEnabled ? AccountStatus::ACTIVE : AccountStatus::INACTIVE;

            if ($currentUserConnected instanceof UserInterface
                && $currentUserConnected->getId() === $user->getId()
                && false === $isEnabled
            ) {
                return $this->createTranslatedFlashJsonResponse(
                    $translator,
                    type: 'danger',
                    titleKey: 'user_action.common.error_title',
                    messageKey: 'user_action.toggle.cannot_deactivate_self',
                    statusCode: Response::HTTP_BAD_REQUEST,
                );
            }

            // The actual mutation happens in ToggleUserAccountHandler,
            // dispatched below — not here, on an in-memory object that is
            // never saved in this request.
            $this->dispatchToggleUserAccountTask($user, $accountStatus, $asyncMethodDispatcher);

            return $this->createFlashJsonResponse(
                type: 'success',
                title: $this->bundleTrans($translator, 'user_action.toggle.success_title'),
                message: $this->buildSuccessMessage($user, $isEnabled, $translator),
                statusCode: Response::HTTP_OK,
            );
        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (\Throwable) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'danger',
                titleKey: 'user_action.common.error_title',
                messageKey: 'user_action.toggle.failed',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    public function regenerateTemporyPasswordUserAction(
        Request $request,
        int|string $id,
        #[CurrentUser] SymfonySecurityUserInterface $currentUserConnected,
        UserManagerInterface $userManager,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->_isXmlHttpRequest($request) || !$request->isMethod('PATCH')) {
            throw new BadRequestHttpException('Unauthorized request. Please use the appropriate PATCH/AJAX channel.');
        }

        if ('' === trim((string) $id)) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'danger',
                titleKey: 'user_action.common.missing_data_title',
                messageKey: 'user_action.common.id_required',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        /** @var UserInterface|null $user */
        $user = $userManager->find($id);

        if (!$user instanceof UserInterface) {
            return $this->createUserNotFoundResponse($translator, $id);
        }

        if (!$user->isEmailVerified()) {
            return $this->createEmailNotVerifiedResponse($translator, $user, 'warning');
        }

        if ($currentUserConnected instanceof UserInterface && $currentUserConnected->getId() === $user->getId()) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'danger',
                titleKey: 'user_action.common.error_title',
                messageKey: 'user_action.regenerate_password.cannot_regenerate_self',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $asyncMethodDispatcher->dispatch(GenerateTemporaryPasswordHandler::class, 'process', [$id]);
        } catch (\Throwable) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'danger',
                titleKey: 'user_action.regenerate_password.failed_title',
                messageKey: 'user_action.regenerate_password.failed',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        return $this->createTranslatedFlashJsonResponse(
            $translator,
            type: 'success',
            titleKey: 'user_action.regenerate_password.success_title',
            messageKey: 'user_action.regenerate_password.success',
            statusCode: Response::HTTP_OK,
        );
    }

    /**
     * The frontend consuming this JSON (any stack — HTML/CSS/JS, a mobile
     * app, …) is responsible for rendering it as an actual flash message,
     * e.g. with Bootstrap's Alert component, or @wlindabla/form_validator.
     *
     * `title` and `message` must already be translated.
     */
    final protected function createFlashJsonResponse(
        string $type,
        string $title,
        string $message,
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        return new JsonResponse(['title' => $title, 'type' => $type, 'message' => $message], $statusCode);
    }

    /**
     * Same as createFlashJsonResponse(), but takes translation keys
     * (domain `WlindablaUserBundle`) and translates them with the given
     * translator.
     *
     * @param array<string, scalar|null> $titleParameters
     * @param array<string, scalar|null> $messageParameters
     */
    final protected function createTranslatedFlashJsonResponse(
        TranslatorInterface $translator,
        string $type,
        string $titleKey,
        string $messageKey,
        array $titleParameters = [],
        array $messageParameters = [],
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        return $this->createFlashJsonResponse(
            type: $type,
            title: $this->bundleTrans($translator, $titleKey, $titleParameters),
            message: $this->bundleTrans($translator, $messageKey, $messageParameters),
            statusCode: $statusCode,
        );
    }

    /**
     * @param array<string, scalar|null> $parameters
     */
    final protected function bundleTrans(TranslatorInterface $translator, string $key, array $parameters = []): string
    {
        return $translator->trans($key, $parameters, 'WlindablaUserBundle');
    }

    final protected function createUserNotFoundResponse(TranslatorInterface $translator, string|int $id): JsonResponse
    {
        return $this->createTranslatedFlashJsonResponse(
            $translator,
            type: 'warning',
            titleKey: 'user_action.common.warning_title',
            messageKey: 'user_action.common.user_not_found',
            messageParameters: ['%id%' => (string) $id],
            statusCode: Response::HTTP_NOT_FOUND,
        );
    }

    final protected function createEmailNotVerifiedResponse(
        TranslatorInterface $translator,
        UserInterface $user,
        string $type,
    ): JsonResponse {
        return $this->createTranslatedFlashJsonResponse(
            $translator,
            type: $type,
            titleKey: 'user_action.common.email_not_verified_title',
            messageKey: 'user_action.common.email_not_verified',
            messageParameters: ['%email%' => $user->getEmail(), '%username%' => $user->getUsername()],
            statusCode: Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * @throws BadRequestHttpException if "status" is missing or not a
     *                                  recognizable boolean value
     */
    protected function getStatusFromRequest(Request $request): bool
    {
        if (!$request->getPayload()->has('status')) {
            throw new BadRequestHttpException('The "status" parameter is missing.');
        }

        $status = $request->getPayload()->get('status');

        if (\is_bool($status)) {
            return $status;
        }

        if (\is_string($status)) {
            $normalized = strtolower($status);

            if (\in_array($normalized, ['true', '1', 'on', 'yes'], true)) {
                return true;
            }

            if (\in_array($normalized, ['false', '0', 'off', 'no'], true)) {
                return false;
            }
        }

        throw new BadRequestHttpException(\sprintf('Invalid status: "%s". Use "true" or "false".', \is_scalar($status) ? $status : get_debug_type($status)));
    }

    final protected function _isXmlHttpRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || $request->request->getBoolean('_xml_http_request')
            || $request->query->getBoolean('_xml_http_request');
    }

    final protected function buildSuccessMessage(UserInterface $user, bool $isEnabled, TranslatorInterface $translator): string
    {
        return $this->bundleTrans(
            $translator,
            $isEnabled ? 'user_action.toggle.activated' : 'user_action.toggle.deactivated',
            ['%username%' => $user->getUsername(), '%id%' => (string) $user->getId()],
        );
    }

    final protected function dispatchToggleUserAccountTask(
        UserInterface $user,
        AccountStatus $accountStatus,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher,
    ): void {
        $asyncMethodDispatcher->dispatch(ToggleUserAccountHandler::class, 'handle', [$user->getId(), $accountStatus]);
    }

    /**
     * Call after validating submitted data (Symfony Validator), before
     * creating a new user — detects an exact duplicate (username/email
     * already in use) and a near-duplicate (similar identity, pg_trgm).
     *
     * With SonataAdmin, call it from preCreate():
     *
     *   protected function preCreate(Request $request, object $newObject): ?Response
     *   {
     *       if (!$request->isMethod('POST')) { return null; }
     *
     *       return $this->validateUser(
     *           new SubmittedUserDto($request->request->get('username'), $request->request->get('email')),
     *           $this->userManager,
     *           $this->userDuplicateFinder,
     *           $this->asyncMethodDispatcher,
     *           $this->translator,
     *       );
     *   }
     */
    protected function validateUser(
        SubmittedUserDto $submittedUserDto,
        UserManagerInterface $userManager,
        UserDuplicateFinderService $userDuplicateFinder,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        TranslatorInterface $translator,
    ): ?Response {
        /** @var UserInterface|null $existingUser */
        $existingUser = $userManager->findOneByUsernameOrEmail(
            $submittedUserDto->submittedUsername,
            $submittedUserDto->submittedUserEmail,
        );

        if (null !== $existingUser) {
            $asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'warning',
                [
                    'This user already exists in the database. Account creation was blocked.',
                    [
                        'email' => $submittedUserDto->submittedUserEmail,
                        'existingAccountId' => $existingUser->getId(),
                    ],
                ],
            );

            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'warning',
                titleKey: 'user_action.validate_user.title',
                messageKey: 'user_action.validate_user.already_exists',
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        $duplicates = $userDuplicateFinder->findDuplicates($submittedUserDto->submittedUsername ?? '');

        if ([] !== $duplicates) {
            $asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'warning',
                [
                    'Account creation blocked: the name is similar to an existing user account.',
                    [
                        'submittedUsername' => $submittedUserDto->submittedUsername,
                        'submittedEmail' => $submittedUserDto->submittedUserEmail,
                        'matchedAccountIds' => array_column($duplicates, 'id'),
                        'trigramScores' => array_column($duplicates, 'trigramScore'),
                    ],
                ],
            );

            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'warning',
                titleKey: 'user_action.validate_user.title',
                messageKey: 'user_action.validate_user.similar_name',
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        return null;
    }

    /**
     * Vote actions (double validation) on an EmailChangeRequest. Combine with
     * MicroUserCRUDActionTrait in your own CRUD controller — same usage
     * pattern, same "authorization is the consuming controller's
     * responsibility" convention.
     *
     * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
     * @package <https://github.com/Agbokoudjo/>
     */
    public function approveAction(
        Request $request,
        int|string $id,
        #[CurrentUser] SymfonySecurityUserInterface $currentUserConnected,
        EmailChangeRequestRepositoryInterface $emailChangeRequestRepository,
        EmailChangeRequestServiceInterface $emailChangeRequestService,
        UserManagerInterface $userManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        return $this->handleValidationDecision($request, $id, $currentUserConnected, true, $emailChangeRequestRepository, $emailChangeRequestService, $userManager, $translator);
    }

    public function rejectAction(
        Request $request,
        int|string $id,
        #[CurrentUser] SymfonySecurityUserInterface $currentUserConnected,
        EmailChangeRequestRepositoryInterface $emailChangeRequestRepository,
        EmailChangeRequestServiceInterface $emailChangeRequestService,
        UserManagerInterface $userManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        return $this->handleValidationDecision($request, $id, $currentUserConnected, false, $emailChangeRequestRepository, $emailChangeRequestService, $userManager, $translator);
    }

    private function handleValidationDecision(
        Request $request,
        int|string $id,
        SymfonySecurityUserInterface $currentUserConnected,
        bool $approved,
        EmailChangeRequestRepositoryInterface $emailChangeRequestRepository,
        EmailChangeRequestServiceInterface $emailChangeRequestService,
        UserManagerInterface $userManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        if (!$this->_isXmlHttpRequest($request) || !$request->isMethod('POST')) {
            throw new BadRequestHttpException('Unauthorized request. Please use the appropriate POST/AJAX channel.');
        }

        $emailChangeRequest = $emailChangeRequestRepository->find($id);

        if (null === $emailChangeRequest) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'warning',
                titleKey: 'email_change_vote.request_not_found_title',
                messageKey: 'email_change_vote.request_not_found',
                messageParameters: ['%id%' => (string) $id],
                statusCode: Response::HTTP_NOT_FOUND,
            );
        }

        $validator = $this->resolveManagedCurrentUser($userManager, $currentUserConnected);
        $comment = trim($request->getPayload()->getString('comment', ''));

        try {
            $emailChangeRequestService->submitValidation(
                request: $emailChangeRequest,
                validator: $validator,
                approved: $approved,
                comment: '' !== $comment ? $comment : null,
            );

            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'success',
                titleKey: $approved ? 'email_change_vote.approved_title' : 'email_change_vote.rejected_title',
                messageKey: $approved ? 'email_change_vote.approved' : 'email_change_vote.rejected',
                statusCode: Response::HTTP_OK,
            );
        } catch (\DomainException $e) {
            // Title is translated; the message is the domain reason, kept as is.
            return $this->createFlashJsonResponse(
                type: 'warning',
                title: $this->bundleTrans($translator, 'email_change_vote.not_recorded_title'),
                message: $e->getMessage(),
                statusCode: Response::HTTP_CONFLICT,
            );
        } catch (\Throwable) {
            return $this->createTranslatedFlashJsonResponse(
                $translator,
                type: 'danger',
                titleKey: 'email_change_vote.failed_title',
                messageKey: 'email_change_vote.failed',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }
        }
}