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

namespace Wlindabla\UserBundle\UI\Http\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorAuthKey;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;

/**
 * Second-factor guard — blocks access to the protected firewall's routes
 * until the authenticated user has validated their MFA code.
 *
 * Guarded by the security firewall (wlindabla_user.session_control.firewall_name,
 * reused — this must be the same perimeter SessionControlListener protects),
 * not by a route name prefix — unlike the previous Sonata-coupled version.
 *
 * Priority 7, BEFORE SessionControlListener (priority 6): while the code
 * isn't validated, no UserSession (single-session policy) should be
 * created for this browser.
 *
 * Typed against this bundle's own UserInterface (which extends
 * TwoFactorCodeForUserInterface), never a concrete project entity — any
 * project's User class works as long as it implements this bundle's
 * interfaces.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class TwoFactorAuthListener
{
    use TargetPathTrait;
    
    public function __construct(
        private RouterInterface $router,
        private TwoFactorCodeManagerInterface $twoFactorCodeManager,
        private AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private UserManagerInterface $userManager,
        private Security $security,
        private LoggerInterface $logger,
        private string $firewallName,
        private int $inactivityThresholdSeconds,
        private int $absoluteCapSeconds,
        private int $activityTouchThrottleSeconds,
        /** @var list<string> */
        private array $allowedRoutesWhilePending = [],
        private bool $supportAjaxJsonResponse = false,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $firewallConfig = $this->security->getFirewallConfig($request);

        if (null === $firewallConfig || $this->firewallName !== $firewallConfig->getName()) {
            return;
        }

        $route = $request->attributes->get('_route');

        if (\in_array($route, $this->allowedRoutesWhilePending, true)) {
            return;
        }

        $user = $this->security->getUser();

        if (null === $user) {
            // Anonymous visitor — not our concern, let the firewall handle authentication.
            return;
        }

        if (!$user instanceof UserInterface) {
            $this->logger->warning('Unexpected user type for MFA freshness check — denying by caution (fail closed).', [
                'user_class' => $user::class,
            ]);
            $event->setResponse($this->buildRedirectResponse($request));
            $event->stopPropagation();
            return;
        }

        $session = $request->getSession();

        if ($session->get(TwoFactorAuthKey::PENDING_2FA->value, false)) {
            // The flag may be stale (code already validated/expired in DB)
            // — remove it if there's nothing left to validate.
            if (null === $this->twoFactorCodeManager->findActivePending($user->getUserIdentifier())) {
                $session->remove(TwoFactorAuthKey::PENDING_2FA->value);

                return;
            }

            $this->logger->info('Access blocked: MFA pending validation.', [
                'user' => $user->getUserIdentifier(),
                'route' => $route,
            ]);

            $event->setResponse($this->buildRedirectResponse($request));
            $event->stopPropagation();

            return;
        }

        // No flag in session: either already validated earlier in this
        // same PHP session (normal case), or — the gap this closes —
        // silently authenticated via remember-me, which never triggers
        // INTERACTIVE_LOGIN and so never sets this flag. Either way, we
        // now fall back to the freshness persisted on the account itself
        // (UserInterface::getTwoFactorValidatedAt()), which survives a PHP
        // session change.
        if (!$this->isTwoFactorFresh($user)) {
            $this->logger->info('Access blocked: MFA freshness expired (inactivity or absolute cap).', [
                'user' => $user->getUserIdentifier(),
                'route' => $route,
            ]);

            $this->forceChallenge($request, $user);

            $event->setResponse($this->buildRedirectResponse($request));
            $event->stopPropagation();

            return;
        }

        $this->touchTwoFactorActivity($user);
    }

    /**
     * Two independent rules, the first one to fail invalidates freshness:
     * - sliding inactivity window;
     * - absolute cap since the last validation.
     *
     * Missing either date (never validated, or an account created before
     * this mechanism existed) → always considered stale, fail closed.
     */
    private function isTwoFactorFresh(UserInterface $user): bool
    {
        $validatedAt = $user->getTwoFactorValidatedAt();
        $lastCheckedAt = $user->getTwoFactorLastCheckedAt();

        if (null === $validatedAt || null === $lastCheckedAt) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (($now->getTimestamp() - $lastCheckedAt->getTimestamp()) > $this->inactivityThresholdSeconds) {
            return false;
        }

        return ($now->getTimestamp() - $validatedAt->getTimestamp()) <= $this->absoluteCapSeconds;
    }

    /**
     * Triggers a new MFA challenge — same mechanism as
     * LoginSubscriber::onRequireTwoFactor(), but callable here for the
     * remember-me case which never goes through INTERACTIVE_LOGIN. The
     * flag is set BEFORE sending, for the same fail-closed reasons as in
     * LoginSubscriber.
     */
    private function forceChallenge(Request $request, UserInterface $user): void
    {
        $request->getSession()->set(TwoFactorAuthKey::PENDING_2FA->value, true);

        try {
            $this->asyncMethodDispatcher->dispatch(
                TwoFactorCodeManagerInterface::class,
                'generateAndSendAsync',
                [$user->getUserIdentifier(), $request->getClientIp(), 'remember_me_refresh'],
            );
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to generate/send the MFA code (periodic revalidation).', [
                'user' => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Updates the activity heartbeat, throttled. Deliberately silent on
     * save failure: an error here must never block an already-legitimate
     * request.
     */
    private function touchTwoFactorActivity(UserInterface $user): void
    {
        $lastCheckedAt = $user->getTwoFactorLastCheckedAt();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (null !== $lastCheckedAt
            && ($now->getTimestamp() - $lastCheckedAt->getTimestamp()) < $this->activityTouchThrottleSeconds
        ) {
            return;
        }

        try {
            /**
             * @var UserInterface|null $managedUser
             */
            $managedUser = $this->userManager->find($user->getId());

            if (!$managedUser instanceof UserInterface) {
                $this->logger->error('Failed to update the MFA activity heartbeat: user no longer exists.', [
                    'user' => $user->getUserIdentifier(),
                ]);

                return;
            }

            $managedUser->setTwoFactorLastCheckedAt($now);
            $this->userManager->save($managedUser);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update the MFA activity heartbeat (non-blocking).', [
                'user' => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildRedirectResponse(Request $request): Response
    {
        // The target_path is never written by a background XHR call,
        // regardless of the mode — it must always reflect the actual
        // primary navigation that triggered this blockage.
        if (!$request->isXmlHttpRequest()) {
            $this->saveTargetPath($request->getSession(), $this->firewallName, $request->getUri());
        }

        if ($this->supportAjaxJsonResponse && $request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'twoFactorRequired' => true,
                'redirect' => $this->router->generate('wlindabla_user_two_factor_show'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        // supportAjaxJsonResponse === false: the bundle no longer handles AJAX
        // differently — it returns a standard redirect even for an
        // XHR call. It is up to the host frontend to detect `response.redirected`
        // / `xhr.responseURL` and to force `window.location.href` itself.
        return new RedirectResponse($this->router->generate('wlindabla_user_two_factor_show'));
    }
}