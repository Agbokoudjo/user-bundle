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

namespace Wlindabla\UserBundle\Infrastructure\Persistance;

use Symfony\Component\Lock\LockFactory;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Application\Service\SessionStoreEvictorInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Domain\UserSession\Model\Persistance\UserSessionRepositoryInterface;
use Wlindabla\UserBundle\Domain\UserSession\Model\UserSessionInterface;

/**
 * Orchestrates session operations between the database (repository) and
 * Redis (session store). The repository only knows Doctrine; this service
 * coordinates both infrastructures.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class UserSessionManager implements UserSessionManagerInterface
{
    public function __construct(
        private readonly UserSessionRepositoryInterface $repository,
        private readonly LockFactory $lockFactory,
        private readonly SessionStoreEvictorInterface $sessionStoreEvictor
    ) {
    }
    
    public function createSession(
        string $userIdentifier,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null,
    ): UserSessionInterface {
        // Locked on $userIdentifier, not $sessionId: the race this guards
        // against is two concurrent logins for the SAME user each revoking
        // and recreating a session — locking per sessionId (different for
        // each login attempt) gave no real protection against that.
        $lock = $this->lockFactory->createLock('user_session_create_'.$userIdentifier, ttl: 5);
        $lock->acquire(true);

        try {
            $this->revokeAllActiveSessions($userIdentifier, UserSessionRevokedReason::CONFLICT);

            return $this->repository->createSession(
                $userIdentifier,
                $sessionId,
                $ipAddress,
                $userAgent,
                $deviceFingerprint,
            );
        } finally {
            $lock->release();
        }
    }

    public function getSessionInfo(string $userIdentifier): ?array
    {
        $session = $this->repository->findActiveSession($userIdentifier);

        if (null === $session) {
            return null;
        }

        return [
            'id' => $session->getId(),
            'sessionId' => $session->getSessionId(),
            'ipAddress' => $session->getIpAddress(),
            'userAgent' => $session->getUserAgent(),
            'deviceFingerprint' => $session->getDeviceFingerprint(),
            'createdAt' => $session->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'lastActivityAt' => $session->getLastActivityAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function revokeAllActiveSessions(
        string $userIdentifier,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::CONFLICT,
    ): void {
        $revokedSessionIds = $this->repository->revokeAllActiveSessions($userIdentifier, $reason);

        foreach ($revokedSessionIds as $sessionId) {
             $this->sessionStoreEvictor->evict($sessionId);
        }
    }

    public function removeSession(
        string $sessionId,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::MANUAL_LOGOUT,
    ): void {
        $revokedSessionId = $this->repository->removeSession($sessionId, $reason);

        if (null !== $revokedSessionId) {
            $this->sessionStoreEvictor->evict($revokedSessionId);
        }
    }

    public function updateSessionActivity(string $sessionId): void
    {
        $this->repository->updateSessionActivity($sessionId);
    }

    public function cleanExpiredSessions(int $hoursOfInactivity, int $maxDurationHours): array
    {
        $expiredInactivity = $this->repository->markExpiredSessionsAsInactive($hoursOfInactivity);
        $expiredMaxDuration = $this->repository->markSessionsExceedingMaxDuration($maxDurationHours);

        foreach ([...$expiredInactivity, ...$expiredMaxDuration] as $session) {
            $this->sessionStoreEvictor->evict($session->getSessionId());
        }

        return [
            'inactivity' => $expiredInactivity,
            'maxDuration' => $expiredMaxDuration,
        ];
    }

    public function hasActiveSession(string $userIdentifier, string $currentSessionId): bool
    {
        return $this->repository->hasActiveSession($userIdentifier, $currentSessionId);
    }

    public function findActiveSession(string $userIdentifier): ?UserSessionInterface
    {
        return $this->repository->findActiveSession($userIdentifier);
    }

    public function findBySessionId(string $sessionId): ?UserSessionInterface
    {
        return $this->repository->findBySessionId($sessionId);
    }

    public function createRevokedSession(
        string $userIdentifier,
        string $sessionId,
        UserSessionRevokedReason $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null,
        ): UserSessionInterface {
        return $this->repository->createRevokedSession(
            $userIdentifier,
            $sessionId,
            $reason,
            $ipAddress,
            $userAgent,
            $deviceFingerprint,
        );
    }

    public function revokeEverySession(
        UserSessionRevokedReason $reason = UserSessionRevokedReason::MANUAL_LOGOUT,
    ): int {
        $revokedSessionIds = $this->repository->revokeEverySession($reason);

        foreach ($revokedSessionIds as $sessionId) {
            $this->sessionStoreEvictor->evict($sessionId);
        }

        return \count($revokedSessionIds);
    }
}