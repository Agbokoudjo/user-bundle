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

namespace Wlindabla\UserBundle\Domain\UserSession\Application\Manager;

use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Domain\UserSession\Model\UserSessionInterface;

/**
 * Service contract for managing single user sessions.
 *
 * All state-mutating methods (create, update, remove) are
 * dispatchable asynchronously via AsyncMethodDispatcherInterface.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package Wlindabla\UserBundle\Domain\UserSession\Application\Manager
 */
interface UserSessionManagerInterface
{
    /**
     * Creates a new session for the identified user and revokes (reason: CONFLICT)
     * all previous active sessions to enforce a single-session policy.
     *
     * @param string $userIdentifier Unique user identifier (e.g., email or username)
     * @param string $sessionId The PHP session ID
     * @param string|null $ipAddress Client IP address
     * @param string|null $userAgent HTTP User-Agent header
     * @param string|null $deviceFingerprint Unique device fingerprint identifier
     *
     * @return UserSessionInterface The newly created user session instance
     */
    public function createSession(
        string $userIdentifier,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null,
    ): UserSessionInterface;

    /**
     * Returns the most recent active session for a given user,
     * or null if no active session exists.
     *
     * @param string $userIdentifier Unique user identifier
     *
     * @return UserSessionInterface|null The active session or null
     */
    public function findActiveSession(string $userIdentifier): ?UserSessionInterface;

    /**
     * Retrieves a session by its PHPSESSID, whether active OR revoked.
     * Allows SessionControlListener to distinguish between "never created" and "revoked".
     *
     * @param string $sessionId The PHP session ID
     *
     * @return UserSessionInterface|null The matching session or null
     */
    public function findBySessionId(string $sessionId): ?UserSessionInterface;

    /**
     * Determines whether the user holds an active session with an ID
     * DIFFERENT from $currentSessionId (indicates a session conflict).
     *
     * @param string $userIdentifier Unique user identifier
     * @param string $currentSessionId Current active session ID to compare against
     *
     * @return bool True if a active session conflict exists, false otherwise
     */
    public function hasActiveSession(string $userIdentifier, string $currentSessionId): bool;

    /**
     * Returns metadata for the user's active session as a normalized array,
     * or null if no active session exists.
     *
     * @param string $userIdentifier Unique user identifier
     *
     * @return array<string, mixed>|null The normalized session metadata array or null
     */
    public function getSessionInfo(string $userIdentifier): ?array;

    /**
     * Soft-revokes:
     *  - Active sessions inactive for longer than $hoursOfInactivity hours
     *    (reason: EXPIRED_INACTIVITY);
     *  - Active sessions created more than $maxDurationHours hours ago,
     *    even if the user remained continuously active (reason: EXPIRED_MAX_DURATION) —
     *    aligned with the remember_me cookie lifetime.
     *
     * Periodically invoked by the Symfony Scheduler.
     *
     * @param int $hoursOfInactivity Inactivity threshold in hours
     * @param int $maxDurationHours Maximum overall session duration threshold in hours
     *
     * @return array{inactivity: UserSessionInterface[], maxDuration: UserSessionInterface[]}
     */
    public function cleanExpiredSessions(int $hoursOfInactivity, int $maxDurationHours): array;

    /**
     * Revokes all active sessions for a user with the specified reason.
     * Also used during explicit logout.
     *
     * @param string $userIdentifier Unique user identifier
     * @param UserSessionRevokedReason $reason The revocation reason enum
     *
     * @return void
     */
    public function revokeAllActiveSessions(
        string $userIdentifier,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::CONFLICT,
    ): void;

    /**
     * Revokes a specific session by its PHP session ID.
     * Called during logout (LogoutSubscriber).
     *
     * @param string $sessionId The PHP session ID to revoke
     * @param UserSessionRevokedReason $reason The revocation reason enum
     *
     * @return void
     */
    public function removeSession(
        string $sessionId,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::MANUAL_LOGOUT,
    ): void;

    /**
     * Updates the last activity timestamp for the session.
     *
     * Uses a cache lock to rate-limit database SQL writes to a maximum of once per hour
     * (prevents flushing on every HTTP request).
     *
     * @param string $sessionId The PHP session ID
     *
     * @return void
     */
    public function updateSessionActivity(string $sessionId): void;
}