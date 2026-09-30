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
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;

/**
 * Révoque la ligne UserSession (raison MANUAL_LOGOUT) lors d'une
 * déconnexion volontaire, pour que la politique single-session reste
 * cohérente immédiatement (pas d'attente du scheduler d'inactivité).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
#[AsEventListener(event: LogoutEvent::class)]
final readonly class LogoutSubscriber
{
    public function __construct(
        private UserSessionManagerInterface $sessionManager,
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

        try {
            $this->sessionManager->removeSession($session->getId(), UserSessionRevokedReason::MANUAL_LOGOUT);
        } catch (\Throwable $throwable) {
            $this->logger->error('Erreur lors de la révocation de session au logout', [
                'exception_message' => $throwable->getMessage(),
            ]);
        }
    }
}
