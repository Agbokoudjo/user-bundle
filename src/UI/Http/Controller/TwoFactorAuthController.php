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

namespace Wlindabla\UserBundle\UI\Http\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorAuthKey;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\InvalidTwoFactorCodeException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorCodeLockedException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorSendThrottledException;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Wlindabla\UserBundle\Infrastructure\Routing\GeneratesOptionalRouteUrlTrait;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;
use Wlindabla\UserBundle\UI\Http\Controller\Trait\ResolvesManagedCurrentUserTrait;

/**
 * Second-factor entry screen (digits + letters, sent by email). These
 * three routes are deliberately excluded from SessionControlListener and
 * explicitly allowed by TwoFactorAuthListener while 2FA is pending (see
 * wlindabla_user.two_factor.allowed_routes_while_pending).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsController]
final class TwoFactorAuthController extends AbstractController
{
    use ResolvesManagedCurrentUserTrait;
    use GeneratesOptionalRouteUrlTrait;

    public function __construct(
        private readonly TwoFactorCodeManagerInterface $twoFactorCodeManager,
        private readonly UserManagerInterface $userManager,
        private readonly CacheUserProviderInterface $userProvider,
        private readonly TemplateRegistryInterface $templateRegistry,
        private readonly TranslatorInterface $translator,
        private readonly \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly ?string $loginRouteName = null,
        private readonly ?string $homeRouteName = null,
        private readonly ?string $logoutRouteName = null
    ) {}

    #[Route('/admin/2fa/show', name: 'wlindabla_user_two_factor_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        /** @var UserInterface|null $user */
        $user = $this->getUser();

        if (null === $user) {
            return $this->redirect($this->safeGenerateUrl($this->loginRouteName, $this->urlGenerator, $this->logger) ?? '/');
        }

        // Nothing pending (already validated, or a direct hit without
        // going through login first) → no reason to be here.
        if (null === $this->twoFactorCodeManager->findActivePending($user->getUserIdentifier())
            && !$request->getSession()->get(TwoFactorAuthKey::PENDING_2FA->value, false)
        ) {
            return $this->redirect($this->safeGenerateUrl($this->homeRouteName, $this->urlGenerator,$this->logger) ?? '/');
        }

        return $this->render($this->templateRegistry->get('two_factor_show'), [
            'username' => $user->getUsername(),
            'logout_url' => $this->safeGenerateUrl($this->logoutRouteName,$this->urlGenerator, $this->logger),
        ]);
    }

    #[Route('/admin/2fa/verify', name: 'wlindabla_user_two_factor_verify', methods: ['POST'])]
    public function verify(Request $request): Response
    {
        /** @var UserInterface|null $user */
        $user = $this->getUser();

        if (null === $user) {
            return $this->jsonOrRedirectToLogin($request, $this->trans('two_factor.session_expired_login'));
        }

        if (!$this->isCsrfTokenValid('two_factor_verify', $request->request->get('_csrf_token'))) {
            return $this->respondInvalid($request, $this->trans('two_factor.session_expired_retry'));
        }

        $submittedCode = trim((string) $request->request->get('code', ''));

        if ('' === $submittedCode) {
            return $this->respondInvalid($request, $this->trans('two_factor.code_required'));
        }

        try {
            $this->twoFactorCodeManager->verify($user->getUserIdentifier(), $submittedCode);
        } catch (TwoFactorCodeLockedException $e) {
            return $this->respondInvalid(
                $request,
                $this->translator->trans($e->getTranslationKey(), $e->getTranslationParameters(), 'WlindablaUserBundle'),
                Response::HTTP_LOCKED,
            );
        } catch (InvalidTwoFactorCodeException $e) {
            return $this->respondInvalid(
                $request,
                $this->translator->trans($e->getTranslationKey(), $e->getTranslationParameters(), 'WlindablaUserBundle'),
            );
        }

        // Session ID regeneration: a new one is issued once the second
        // factor is validated (classic trap: never keep the sessionId
        // that predates validation).
        $request->getSession()->migrate(true);
        $request->getSession()->remove(TwoFactorAuthKey::PENDING_2FA->value);

        // MFA freshness persisted on the ACCOUNT (not the PHP session) —
        // this is what TwoFactorAuthListener rechecks on subsequent
        // requests, including ones authenticated by remember-me.
        //
        // resolveManagedCurrentUser() reloads $user as an entity actually
        // managed by Doctrine — see its own docblock for the pitfall this
        // avoids. Deliberately non-blocking here: a reload failure at this
        // exact moment must never break a login flow that just succeeded
        // its code verification.
        try {
            $managedUser = $this->resolveManagedCurrentUser($this->userManager, $user);
            $managedUser->markTwoFactorValidated();
            $this->userManager->save($managedUser);

            // Required: UserProvider::refreshUser() serves a cached copy
            // on every subsequent request, including the redirect to the
            // dashboard right after. Without this invalidation,
            // TwoFactorAuthListener would keep seeing the old (often null)
            // twoFactorValidatedAt and loop back to requesting a code,
            // despite the successful save above.
            $this->userProvider->invalidateUserCache($managedUser->getId());
        } catch (\Throwable $e) {
            $this->logger->error('Failed to reload the current user to persist MFA freshness.', [
                'user' => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('2FA validated, session regenerated.', ['user' => $user->getUserIdentifier()]);

        return $this->jsonOrRedirectHome($request, $this->trans('two_factor.verified'));
    }

    #[Route('/admin/2fa/resend', name: 'wlindabla_user_two_factor_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        #[Target('two_factor_resend')]
        RateLimiterFactoryInterface $resendLimiter,
    ): Response {
        /** @var UserInterface|null $user */
        $user = $this->getUser();

        if (null === $user) {
            return $this->jsonOrRedirectToLogin($request, $this->trans('two_factor.session_expired_login'));
        }

        if (!$this->isCsrfTokenValid('two_factor_resend', $request->headers->get('X-CSRF-Token'))) {
            return $this->jsonOrRedirect($request, false, $this->trans('two_factor.session_expired_retry'), 'app_two_factor_show');
        }

        $limiter = $resendLimiter->create($request->getClientIp());

        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(retryAfter: 60, message: $this->trans('two_factor.rate_limited'));
        }

        try {
            $this->twoFactorCodeManager->generateAndSend($user->getUserIdentifier(), $request->getClientIp(), 'resend');
        } catch (TwoFactorSendThrottledException $e) {
            return $this->respondInvalid(
                $request,
                $this->translator->trans($e->getTranslationKey(), $e->getTranslationParameters(), 'WlindablaUserBundle'),
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $request->getSession()->set(TwoFactorAuthKey::PENDING_2FA->value, true);

        $message = $this->trans('two_factor.resend_success');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'message' => $message]);
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('wlindabla_user_two_factor_show');
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'WlindablaUserBundle');
    }

    private function respondInvalid(Request $request, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'errorMessage' => $message], $status);
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('wlindabla_user_two_factor_show');
    }

    private function jsonOrRedirectToLogin(Request $request, string $message): Response
    {
        $url = $this->safeGenerateUrl($this->loginRouteName, $this->urlGenerator,$this->logger) ?? '/';

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => $message, 'redirect' => $url], Response::HTTP_UNAUTHORIZED);
        }

        $this->addFlash('danger', $message);

        return $this->redirect($url);
    }

    private function jsonOrRedirectHome(Request $request, string $message): Response
    {
        $url = $this->safeGenerateUrl($this->homeRouteName, $this->urlGenerator,$this->logger) ?? '/';

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'message' => $message, 'redirect' => $url], Response::HTTP_OK);
        }

        return $this->redirect($url);
    }

    private function jsonOrRedirect(Request $request, bool $success, string $message, string $route): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => $success,
                'message' => $message,
                'redirect' => $this->generateUrl($route),
            ], $success ? Response::HTTP_OK : Response::HTTP_UNAUTHORIZED);
        }

        if (!$success) {
            $this->addFlash('danger', $message);
        }

        return $this->redirectToRoute($route);
    }
}