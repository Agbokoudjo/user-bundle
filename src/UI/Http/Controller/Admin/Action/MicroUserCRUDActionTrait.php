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
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\GenerateTemporaryPasswordHandler;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\ToggleUserAccountHandler;
use Wlindabla\UserBundle\Domain\User\Application\Dto\SubmittedUserDto;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Enum\AccountStatus;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Wlindabla\UserBundle\Infrastructure\Service\UserDuplicateFinderService;

/**
 * Ready-to-use account-management actions (resend verification, toggle
 * enabled/disabled, regenerate a temporary password) for a user CRUD
 * controller — independent of any specific admin framework.
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
    public function resendEmailVerifyAction(
        Request $request,
        string|int $id,
        EmailVerificationInterface $verificationService,
        UserManagerInterface $userManager,
    ): JsonResponse {
        if (!$this->isXmlHttpRequest($request) || !$request->isMethod('GET')) {
            throw new BadRequestHttpException('Requête non autorisée. Veuillez utiliser le canal GET/AJAX approprié.');
        }

        try {
            if ('' === trim((string) $id)) {
                throw new BadRequestHttpException('L\'identifiant de l\'utilisateur est manquant.');
            }

            /** @var UserInterface|null $user */
            $user = $userManager->find($id);

            if (!$user instanceof UserInterface) {
                return $this->createFlashJsonResponse(
                    type: 'warning',
                    title: 'Attention',
                    message: \sprintf('Aucun utilisateur trouvé avec l\'identifiant "%s".', $id),
                    statusCode: Response::HTTP_NOT_FOUND,
                );
            }

            if ($user->isEmailVerified()) {
                return $this->createFlashJsonResponse(
                    type: 'warning',
                    title: 'Action bloquée : email déjà vérifié',
                    message: \sprintf(
                        'L\'adresse email "%s" associée au compte "%s" est déjà vérifiée. Cette action est donc bloquée.',
                        $user->getEmail(),
                        $user->getUsername(),
                    ),
                    statusCode: Response::HTTP_FORBIDDEN,
                );
            }

            $verificationService->resendVerificationEmail($id);

            $type = 'success';
            $title = 'Email de vérification envoyé !';
            $message = 'Le lien de vérification a été renvoyé avec succès. Veuillez informer l\'utilisateur de consulter sa boîte de réception ou ses spams pour confirmer son compte.';
            $statusHttp = Response::HTTP_OK;
        } catch (BadRequestHttpException|InvalidTokenException|\Throwable $e) {
            $type = 'danger';
            $title = 'Échec de l\'envoi';
            $message = $e->getMessage();
            $statusHttp = Response::HTTP_BAD_REQUEST;
        }

        return $this->createFlashJsonResponse(type: $type, title: $title, message: $message, statusCode: $statusHttp);
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
    ): JsonResponse {
        if (!$this->isXmlHttpRequest($request) || !$request->isMethod('PATCH')) {
            throw new BadRequestHttpException('Requête non autorisée. Veuillez utiliser le canal PATCH/AJAX approprié.');
        }

        try {
            if ('' === trim((string) $id)) {
                throw new BadRequestHttpException('L\'identifiant de l\'utilisateur est manquant.');
            }

            /** @var UserInterface|null $user */
            $user = $userManager->find($id);

            if (!$user instanceof UserInterface) {
                return $this->createFlashJsonResponse(
                    type: 'warning',
                    title: 'Attention',
                    message: \sprintf('Aucun utilisateur trouvé avec l\'identifiant "%s".', $id),
                    statusCode: Response::HTTP_NOT_FOUND,
                );
            }

            if (!$user->isEmailVerified()) {
                return $this->createFlashJsonResponse(
                    type: 'danger',
                    title: 'Action bloquée : email non vérifié',
                    message: \sprintf(
                        'L\'adresse email "%s" associée au compte "%s" n\'a pas été vérifiée. Cette action est donc bloquée. Veuillez cliquer sur "Renvoyer l\'email de vérification" pour procéder.',
                        $user->getEmail(),
                        $user->getUsername(),
                    ),
                    statusCode: Response::HTTP_FORBIDDEN,
                );
            }

            $isEnabled = $this->getStatusFromRequest($request);
            $accountStatus = $isEnabled ? AccountStatus::ACTIVE : AccountStatus::INACTIVE;

            if ($currentUserConnected instanceof UserInterface
                && $currentUserConnected->getId() === $user->getId()
                && false === $isEnabled
            ) {
                return $this->createFlashJsonResponse(
                    type: 'danger',
                    title: 'Erreur',
                    message: 'Impossible de désactiver son propre compte en étant connecté avec.',
                    statusCode: Response::HTTP_BAD_REQUEST,
                );
            }

            // The actual mutation happens in ToggleUserAccountHandler,
            // dispatched below — not here, on an in-memory object that is
            // never saved in this request.
            $this->dispatchToggleUserAccountTask($user, $accountStatus, $asyncMethodDispatcher);

            return $this->createFlashJsonResponse(
                type: 'success',
                title: 'Opération réussie',
                message: $this->buildSuccessMessage($user, $isEnabled),
                statusCode: Response::HTTP_OK,
            );
        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->createFlashJsonResponse(
                type: 'danger',
                title: 'Erreur',
                message: 'Une erreur est survenue lors de la mise à jour du statut. Veuillez actualiser et réessayer.',
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
    ): JsonResponse {
        if (!$this->isXmlHttpRequest($request) || !$request->isMethod('PATCH')) {
            throw new BadRequestHttpException('Requête non autorisée. Veuillez utiliser le canal PATCH/AJAX approprié.');
        }

        if ('' === trim((string) $id)) {
            return $this->createFlashJsonResponse(
                type: 'danger',
                title: 'Donnée manquante',
                message: 'L\'identifiant utilisateur (ID) est requis et ne peut être vide.',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        /** @var UserInterface|null $user */
        $user = $userManager->find($id);

        if (!$user instanceof UserInterface) {
            return $this->createFlashJsonResponse(
                type: 'warning',
                title: 'Attention',
                message: \sprintf('Aucun utilisateur trouvé avec l\'identifiant "%s".', $id),
                statusCode: Response::HTTP_NOT_FOUND,
            );
        }

        if (!$user->isEmailVerified()) {
            return $this->createFlashJsonResponse(
                type: 'warning',
                title: 'Action bloquée : email non vérifié',
                message: \sprintf(
                    'L\'adresse email "%s" associée au compte "%s" n\'a pas été vérifiée. Cette action est donc bloquée. Veuillez cliquer sur "Renvoyer l\'email de vérification" pour procéder.',
                    $user->getEmail(),
                    $user->getUsername(),
                ),
                statusCode: Response::HTTP_FORBIDDEN,
            );
        }

        if ($currentUserConnected instanceof UserInterface && $currentUserConnected->getId() === $user->getId()) {
            return $this->createFlashJsonResponse(
                type: 'danger',
                title: 'Erreur',
                message: 'Impossible de régénérer son propre mot de passe en étant connecté. Utilisez "Mot de passe oublié" depuis la page de connexion.',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $asyncMethodDispatcher->dispatch(GenerateTemporaryPasswordHandler::class, 'process', [$id]);

            $type = 'success';
            $title = 'Mot de passe temporaire généré et envoyé !';
            $message = 'Un nouveau mot de passe temporaire a été généré avec succès. Une notification a été immédiatement envoyée à la boîte e-mail de l\'utilisateur pour qu\'il puisse se connecter et le modifier.';
            $statusHttp = Response::HTTP_OK;
        } catch (\Throwable $e) {
            $type = 'danger';
            $title = 'Échec de la réinitialisation';
            $message = 'Une erreur est survenue lors de la régénération du mot de passe. Veuillez réessayer.';
            $statusHttp = Response::HTTP_BAD_REQUEST;
        }

        return $this->createFlashJsonResponse(type: $type, title: $title, message: $message, statusCode: $statusHttp);
    }

    /**
     * The frontend consuming this JSON (any stack — HTML/CSS/JS, a mobile
     * app, …) is responsible for rendering it as an actual flash message,
     * e.g. with Bootstrap's Alert component, or @wlindabla/form_validator.
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
     * @throws BadRequestHttpException if "status" is missing or not a
     *                                  recognizable boolean value
     */
    protected function getStatusFromRequest(Request $request): bool
    {
        if (!$request->getPayload()->has('status')) {
            throw new BadRequestHttpException('Le paramètre "status" est manquant.');
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

        throw new BadRequestHttpException(\sprintf('Statut invalide : "%s". Utiliser "true" ou "false".', $status));
    }

    final protected function isXmlHttpRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || $request->request->getBoolean('_xml_http_request')
            || $request->query->getBoolean('_xml_http_request');
    }

    final protected function buildSuccessMessage(UserInterface $user, bool $isEnabled): string
    {
        $action = $isEnabled ? 'Compte activé avec succès' : 'Compte désactivé avec succès';

        return \sprintf('%s - %s (ID: %s)', $action, $user->getUsername(), $user->getId());
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
     *       );
     *   }
     */
    protected function validateUser(
        SubmittedUserDto $submittedUserDto,
        UserManagerInterface $userManager,
        UserDuplicateFinderService $userDuplicateFinder,
        AsyncMethodDispatcherInterface $asyncMethodDispatcher,
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
                    'Cet utilisateur existe déjà dans la base de données. La création de compte a été bloquée.',
                    [
                        'email' => $submittedUserDto->submittedUserEmail,
                        'existingAccountId' => $existingUser->getId(),
                    ],
                ],
            );

            return $this->createFlashJsonResponse(
                type: 'warning',
                title: 'Création de compte non traitée',
                message: 'Cet utilisateur existe déjà dans la base de données. La création de compte a été bloquée.',
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        $duplicates = $userDuplicateFinder->findDuplicates($submittedUserDto->submittedUsername ?? '');

        if ([] !== $duplicates) {
            $asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'warning',
                [
                    'Création de compte bloquée : nom similaire à un compte utilisateur existant.',
                    [
                        'submittedUsername' => $submittedUserDto->submittedUsername,
                        'submittedEmail' => $submittedUserDto->submittedUserEmail,
                        'matchedAccountIds' => array_column($duplicates, 'id'),
                        'trigramScores' => array_column($duplicates, 'trigramScore'),
                    ],
                ],
            );

            return $this->createFlashJsonResponse(
                type: 'warning',
                title: 'Création de compte non traitée',
                message: 'Création de compte bloquée : le nom de cet utilisateur est similaire à un compte existant.',
                statusCode: Response::HTTP_CONFLICT,
            );
        }

        return null;
    }
}