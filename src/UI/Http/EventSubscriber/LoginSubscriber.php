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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Browser\DeviceFingerprintUserAgent;
use Wlindabla\ShareServiceBundle\Security\Browser\UserAgentParserInterface;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Application\Manager\LoggerLoginUserManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorAuthKey;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Subscriber for logging successful login attempts.
 *
 * Responsibilities:
 * - log successful logins (LoggerLoginUser) asynchronously
 * - set a "session pending registration" flag in the PHP session,
 *   consumed by SessionControlListener on the NEXT request (which will
 *   see the FINAL sessionId, after Symfony Security's session fixation
 *   strategy has regenerated it).
 *
 * @internal called automatically by Symfony Security
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class LoginSubscriber implements EventSubscriberInterface
{
    use DeviceFingerprintUserAgent;

    /**
     * Session key used to pass login info to SessionControlListener,
     * which consumes it on the next request (once) to create the final
     * UserSession record.
     */
    public const string PENDING_SESSION_REGISTRATION = '_pending_session_registration';

    public function __construct(
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private readonly UserAgentParserInterface $userAgentParser,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => [
                ['onLastLoginUpdate', 0],
                // Must run BEFORE onMarkPendingSession: if a second
                // factor is required, the session must not yet be
                // considered "pending final registration" (TwoFactorAuthListener
                // will block access to protected routes anyway while
                // PENDING_2FA is set).
                ['onRequireTwoFactor', 10],
                ['onMarkPendingSession', 0],
            ],
        ];
    }

    /**
     * Correct password → triggers sending the MFA code (by email) and
     * flags the current PHP session as "2FA pending". TwoFactorAuthListener
     * then blocks access to protected routes until the code is validated.
     */
    public function onRequireTwoFactor(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof BaseUserInterface) {
            return;
        }

        $request = $event->getRequest();

        // The flag is set BEFORE sending, deliberately: if the email
        // fails but the code was already persisted (generateAndSend
        // flushes to DB before attempting to send), TwoFactorAuthListener
        // must still block access. Fail closed rather than let a login
        // through without a second factor because of an SMTP incident.
        $request->getSession()->set(TwoFactorAuthKey::PENDING_2FA->value, true);

        try {
            $this->asyncMethodDispatcher->dispatch(
                TwoFactorCodeManagerInterface::class,
                'generateAndSend',
                [$user->getUserIdentifier(), $request->getClientIp(), 'login'],
            );
        } catch (\Throwable $e) {
            $this->logger?->critical('Failed to generate/send the MFA code at login.', [
                'user' => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sets the "pending registration" flag.
     *
     * Does NOT create the UserSession here: at this point in the
     * lifecycle, the sessionId isn't final yet (the session fixation
     * strategy regenerates it right after authentication).
     * SessionControlListener finalizes the registration on the next
     * request, with the definitive sessionId.
     */
    public function onMarkPendingSession(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof BaseUserInterface) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $session->set(self::PENDING_SESSION_REGISTRATION, [
            'userIdentifier' => $user->getUserIdentifier(),
            'ipAddress' => $request->getClientIp(),
            'userAgent' => $request->headers->get('User-Agent'),
            'deviceFingerprint' => $this->buildDeviceFingerprint($request),
        ]);
    }

    /**
     * Logs a successful login (username, email, IP, createdAt auto).
     */
    public function onLastLoginUpdate(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof BaseUserInterface) {
            $this->logger?->debug('Invalid user for login logging.', [
                'user_class' => $user ? $user::class : 'null',
            ]);

            return;
        }

        try {
            $rawUserAgent = $event->getRequest()->headers->get('User-Agent');
            $userAgentLabel = $this->userAgentParser->parse($rawUserAgent);

            $this->asyncMethodDispatcher->dispatch(
                LoggerLoginUserManagerInterface::class,
                'createAndSave',
                [
                    $user->getUsername(),
                    $user->getEmail(),
                    $event->getRequest()->getClientIp(),
                    $rawUserAgent,
                    $userAgentLabel,
                    new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                ],
            );

            $this->logger?->info('Login logging dispatched.', [
                'user_id' => $user->getId(),
                'ip' => $event->getRequest()->getClientIp(),
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to persist the login log.', [
                'exception' => $e->getMessage(),
                'username' => $user->getUsername(),
            ]);
        }
    }
}