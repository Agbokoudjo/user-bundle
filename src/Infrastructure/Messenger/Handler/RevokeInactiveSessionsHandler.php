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

namespace Wlindabla\UserBundle\Infrastructure\Messenger\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Wlindabla\UserBundle\Domain\UserSession\Model\Persistance\UserSessionRepositoryInterface;
use Wlindabla\UserBundle\Infrastructure\Messenger\Message\RevokeInactiveSessionsMessage;

/**
 * Was missing: SessionCleanupSchedule dispatches RevokeInactiveSessionsMessage
 * periodically, but nothing consumed it — the message was never processed.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsMessageHandler]
final class RevokeInactiveSessionsHandler
{
    public function __construct(
        private readonly UserSessionRepositoryInterface $sessionManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RevokeInactiveSessionsMessage $message): void
    {
        $result = $this->sessionManager->cleanExpiredSessions(
            $message->inactivityHours,
            $message->maxDurationHours,
        );

        $this->logger->info('Expired sessions cleanup ran.', [
            'revoked_for_inactivity' => \count($result['inactivity']),
            'revoked_for_max_duration' => \count($result['maxDuration']),
        ]);
    }
}