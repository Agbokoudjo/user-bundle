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
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;

/**
* Revokes the UserSession record (reason: MANUAL_LOGOUT) upon voluntary
* logout, ensuring the single-session policy remains consistent
* immediately (without waiting for the inactivity scheduler). 
*
* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
*/
#[AsEventListener(event: LogoutEvent::class,priority: 10)]
final readonly class LogoutSubscriber
{
    public function __construct(
        private UserSessionManagerInterface $sessionManager,
        private CacheUserProviderInterface $userProvider,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(LogoutEvent $event): void
    {
        $session = $event->getRequest()->hasSession()
            ? $event->getRequest()->getSession()
            : null;

        if ($session === null) {
            return;
        }

         /** @var BaseUserInterface|null $user */
        $user = $event->getToken()?->getUser();

        if (!($user instanceof BaseUserInterface)) {
            return;
        }

        try {
            $this->sessionManager->removeSession($session->getId(), UserSessionRevokedReason::MANUAL_LOGOUT);
            $this->userProvider->invalidateUserCache($user->getId());
        } catch (\Throwable $throwable) {
            $this->logger->error('Error revoking session on logout', [
                'exception_message' => $throwable->getMessage(),
            ]);
        }
    }
}
