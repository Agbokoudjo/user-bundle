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

namespace Wlindabla\UserBundle\Infrastructure\Messenger\Message;

/**
 * Symfony Messenger message, triggered periodically by the Scheduler to
 * clean up expired sessions.
 *
 * Two independent criteria, see UserSessionManagerInterface::cleanExpiredSessions():
 *  - $inactivityHours: revoke if lastActivityAt is past this threshold (a
 *    workstation left open);
 *  - $maxDurationHours: revoke if createdAt is past this threshold, even
 *    for a continuously active session (absolute cap on a session's
 *    lifetime, typically aligned with remember_me.lifetime).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class RevokeInactiveSessionsMessage
{
    public function __construct(
        public readonly int $inactivityHours,
        public readonly int $maxDurationHours,
    ) {
    }
}