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
use Symfony\Contracts\Translation\TranslatorInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Browser\DeviceFingerprintUserAgent;
use Wlindabla\ShareServiceBundle\Security\Encryption\IdEncryptionInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;

/**
 * Single-session guard — checked on every HTTP request that the current
 * user isn't simultaneously logged in on another device, and hasn't
 * exceeded the inactivity or max-duration thresholds.
 *
 * Guarded by the security firewall protecting the request
 * (wlindabla_user.session_control.firewall_name), not by a route name
 * prefix — a route-name-based guard silently stops working the moment the
 * admin's routes are renamed or a dependency like Sonata is removed.
 *
 * Order matters: this must run AFTER the firewall itself, AND after
 * TwoFactorAuthListener — a second factor not yet validated must never
 * let a session be registered for this browser.
 * @block: the new login is refused. A revoked UserSession row (reason: conflict) is created for this browser and
 *         the user is redirected to the conflict page (wlindabla_user_session_conflict), which then revokes that
 *         browser's row.
 * @takeover: revoke the old session and allow the new one to proceed.
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class SessionControlListener
{
    use DeviceFingerprintUserAgent;

    public function __construct(
        private UserSessionManagerInterface $sessionManager,
        private AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private RouterInterface $router,
        private LoggerInterface $logger,
        private IdEncryptionInterface $idEncryptionService,
        private Security $security,
        private TranslatorInterface $translator,
        private string $firewallName,
        private int $maxDurationHours,
        private string $sessionPolicy = 'takeover',
        /** @var list<string> */
        private array $excludedRoutes = []
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        try {
            $firewallConfig = $this->security->getFirewallConfig($request);

            if (null === $firewallConfig || $this->firewallName !== $firewallConfig->getName()) {
                return;
            }

            $route = $request->attributes->get('_route');

            if (\in_array($route, $this->excludedRoutes, true)) {
                return;
            }

            $user = $this->security->getUser();

            if (!$user instanceof BaseUserInterface) {
                return;
            }

            $session = $request->getSession();
            $currentSessionId = $session->getId();

            // STEP 1 — Finalize the registration queued by LoginSubscriber.
            // By this point, session_fixation_strategy has already
            // regenerated the sessionId. $currentSessionId is the FINAL one.
            $pendingData = $session->get(LoginSubscriber::PENDING_SESSION_REGISTRATION);

            if (null !== $pendingData) {
                $session->remove(LoginSubscriber::PENDING_SESSION_REGISTRATION);

                if ('block' === $this->sessionPolicy
                    && $this->sessionManager->hasActiveSession($pendingData['userIdentifier'], $currentSessionId)
                ) {
                    $this->sessionManager->createRevokedSession(
                        $pendingData['userIdentifier'],
                        $currentSessionId,
                        UserSessionRevokedReason::CONFLICT,
                        $pendingData['ipAddress'],
                        $pendingData['userAgent'],
                        $pendingData['deviceFingerprint'],
                    );

                    $this->logger->warning('Login refused: an active session already exists elsewhere.', [
                        'user' => $pendingData['userIdentifier'],
                        'session_id' => $currentSessionId,
                    ]);

                    $event->setResponse($this->buildRevokedResponse($request, $user, UserSessionRevokedReason::CONFLICT));
                    $event->stopPropagation();

                    return;
                }

                $this->sessionManager->createSession(
                    $pendingData['userIdentifier'],
                    $currentSessionId,
                    $pendingData['ipAddress'],
                    $pendingData['userAgent'],
                    $pendingData['deviceFingerprint'],
                );

                $this->logger->info('Session registered in DB (post-login).', [
                    'user' => $pendingData['userIdentifier'],
                    'session_id' => $currentSessionId,
                ]);

                return;
            }

            // STEP 2 — Find this browser's own row by sessionId, active or revoked.
            $ownSession = $this->sessionManager->findBySessionId($currentSessionId);

            if (null === $ownSession) {
                // No row for this sessionId — remember-me restored the
                // user after a clean logout (remember_me cookie still
                // valid). Silent creation.
                $this->sessionManager->createSession(
                    $user->getUserIdentifier(),
                    $currentSessionId,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                    $this->buildDeviceFingerprint($request),
                );

                $this->logger->info('Session created in DB (remember-me / restoration).', [
                    'user' => $user->getUserIdentifier(),
                    'session_id' => $currentSessionId,
                ]);

                return;
            }

            if ($ownSession->isActive()) {
                $this->asyncMethodDispatcher->dispatch(
                    UserSessionManagerInterface::class,
                    'updateSessionActivity',
                    [$currentSessionId],
                );

                return;
            }

            // STEP 3 — This session was revoked in the meantime.
            $reason = $ownSession->getRevokedReason() ?? UserSessionRevokedReason::EXPIRED_INACTIVITY;

            $this->logger->warning('Revoked session detected.', [
                'user' => $user->getUserIdentifier(),
                'session_id' => $currentSessionId,
                'revoked_reason' => $reason->value,
                'route' => $route,
            ]);

            $event->setResponse($this->buildRevokedResponse($request, $user, $reason));
            $event->stopPropagation();
        } catch (\Throwable $throwable) {
            $this->logger->error('SessionControlListener error.', [
                'exception_message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'route' => $request->attributes->get('_route'),
            ]);
        }
    }

    private function buildRevokedResponse(
        Request $request,
        BaseUserInterface $user,
        UserSessionRevokedReason $reason,
    ): Response {
        $translationParameters = UserSessionRevokedReason::EXPIRED_MAX_DURATION === $reason
            ? ['%hours%' => $this->maxDurationHours]
            : [];

        $message = $this->translator->trans($reason->getTranslationKey(), $translationParameters, 'WlindablaUserBundle');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => $reason->value, 'message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        if (UserSessionRevokedReason::CONFLICT === $reason) {
            return new RedirectResponse(
                $this->router->generate('wlindabla_user_session_conflict', [
                    'userId' => $this->idEncryptionService->encryptId($user->getId()),
                ]),
            );
        }

        $session = $request->getSession();
        $session->invalidate();
        $session->getFlashBag()->add('warning', $message);

        return $this->security->logout(false);
    }
}