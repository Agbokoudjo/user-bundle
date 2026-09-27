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

namespace Wlindabla\UserBundle\Domain\UserSession\Application\Persistance;

use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Domain\UserSession\Model\UserSessionInterface;

/**
 * Storage contract for user sessions. Unlike UserManagerInterface (whose
 * methods are generic enough to be resolved dynamically via
 * EntityManagerInterface::getRepository()), these are business-specific
 * operations (revocation, activity throttling, batch expiry) that only
 * your concrete AbstractUserSessionRepository subclass implements — hence
 * an explicit interface, aliased once in your own services.yaml:
 *
 *   Wlindabla\UserBundle\Domain\UserSession\Application\Persistance\UserSessionRepositoryInterface: '@App\Repository\UserSessionRepository'
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface UserSessionRepositoryInterface
{
    public function createSession(
        string $userIdentifier,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null,
    ): UserSessionInterface;

    public function findBySessionId(string $sessionId): ?UserSessionInterface;

    /**
     * @return string[] revoked session IDs
     */
    public function revokeAllActiveSessions(
        string $userIdentifier,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::CONFLICT,
    ): array;

    public function removeSession(
        string $sessionId,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::MANUAL_LOGOUT,
    ): ?string;

    public function updateSessionActivity(string $sessionId): void;

    /**
     * @return UserSessionInterface[]
     */
    public function markExpiredSessionsAsInactive(int $hoursOfInactivity): array;

    /**
     * @return UserSessionInterface[]
     */
    public function markSessionsExceedingMaxDuration(int $maxDurationHours): array;

    public function hasActiveSession(string $userIdentifier, string $currentSessionId): bool;

    public function findActiveSession(string $userIdentifier): ?UserSessionInterface;
}