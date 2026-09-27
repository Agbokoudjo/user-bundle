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

namespace Wlindabla\UserBundle\Infrastructure\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use Wlindabla\UserBundle\Domain\UserSession\Model\UserSessionInterface;

/**
 * Single-session-per-user management.
 *
 * Strategy: never delete (SoftDeleteable + audit), always revoke
 * (active = false) with a traced reason (UserSessionRevokedReason) so
 * SessionControlListener can show the right message to the affected
 * browser on its next request. The cache only avoids repeated DB reads —
 * never the source of truth for conflict detection (see
 * hasActiveSession()).
 *
 * Extend it in your application:
 *
 *   class UserSessionRepository extends AbstractUserSessionRepository
 *   {
 *       public function __construct(ManagerRegistry $registry, CacheItemPoolInterface $cache)
 *       {
 *           parent::__construct($registry, UserSession::class, $cache);
 *       }
 *   }
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractUserSessionRepository extends ServiceEntityRepository
{
    private const string CACHE_PREFIX = 'wlindabla_user_session';
    private const int CACHE_TTL = 3600;
    private const int ACTIVITY_TTL = 3600; // at most 1 DB flush per hour

    /**
     * @param class-string $entityClass FQCN of your concrete UserSession entity
     */
    public function __construct(
        ManagerRegistry $registry,
        string $entityClass,
        private readonly CacheItemPoolInterface $cache,
    ) {
        parent::__construct($registry, $entityClass);
    }

    /**
     * All previously active sessions are revoked (reason CONFLICT) BEFORE
     * creating the new one, so the previous device detects the conflict
     * on its very next request (see SessionControlListener).
     */
    public function createSession(
        string $userIdentifier,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null,
    ): UserSessionInterface {
        $session = $this->newSession();
        $session->setUserIdentifier($userIdentifier);
        $session->setSessionId($sessionId);
        $session->setIpAddress($ipAddress);
        $session->setUserAgent($userAgent);
        $session->setDeviceFingerprint($deviceFingerprint);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($session);
        $entityManager->flush();

        $this->cacheSession($userIdentifier, $session);

        return $session;
    }

    private function newSession(): UserSessionInterface
    {
        $entityClass = $this->getClassName();

        return new $entityClass();
    }

    /**
     * Finds a session by its PHPSESSID, active OR revoked.
     *
     * Unlike findActiveSession(), this does NOT filter on active = true:
     * it's what lets SessionControlListener distinguish "this session
     * never existed in DB" from "this session existed and was revoked in
     * the meantime" (conflict, inactivity, max duration).
     *
     * Direct DB read (no cache): the revocation state itself is what we
     * need here, never a stale one.
     */
    public function findBySessionId(string $sessionId): ?UserSessionInterface
    {
        return $this->findOneBy(['sessionId' => $sessionId]);
    }

    /**
     * @return string[] revoked session IDs, so the caller can clean up Redis
     */
    public function revokeAllActiveSessions(
        string $userIdentifier,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::CONFLICT,
    ): array {
        $sessions = $this->findBy(['userIdentifier' => $userIdentifier, 'active' => true]);

        if ([] === $sessions) {
            return [];
        }

        $revokedSessionIds = [];

        foreach ($sessions as $session) {
            $session->revoke($reason);
            $this->cache->deleteItem($this->activityLockKey($session->getSessionId()));
            $revokedSessionIds[] = $session->getSessionId();
        }

        $this->getEntityManager()->flush();
        $this->cache->deleteItem($this->cacheKey($userIdentifier));

        return $revokedSessionIds;
    }

    public function removeSession(
        string $sessionId,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::MANUAL_LOGOUT,
    ): ?string {
        $session = $this->findOneBy(['sessionId' => $sessionId]);

        if (null === $session) {
            return null;
        }

        $session->revoke($reason);
        $this->getEntityManager()->flush();

        $this->cache->deleteItem($this->cacheKey($session->getUserIdentifier()));
        $this->cache->deleteItem($this->activityLockKey($sessionId));

        return $sessionId;
    }

    public function updateSessionActivity(string $sessionId): void
    {
        $lockKey = $this->activityLockKey($sessionId);
        $lockItem = $this->cache->getItem($lockKey);

        if ($lockItem->isHit()) {
            return;
        }

        $session = $this->findOneBy(['sessionId' => $sessionId, 'active' => true]);

        if (null === $session) {
            return;
        }

        $session->updateActivity();
        $this->getEntityManager()->flush();

        $lockItem->set(true)->expiresAfter(self::ACTIVITY_TTL);
        $this->cache->save($lockItem);

        $this->cache->deleteItem($this->cacheKey($session->getUserIdentifier()));
    }

    /**
     * @return UserSessionInterface[]
     */
    public function markExpiredSessionsAsInactive(int $hoursOfInactivity): array
    {
        $limit = new \DateTimeImmutable(\sprintf('-%d hours', $hoursOfInactivity), new \DateTimeZone('UTC'));

        $expired = $this->createQueryBuilder('s')
            ->where('s.lastActivityAt < :limit')
            ->andWhere('s.active = :active')
            ->setParameter('active', true)
            ->setParameter('limit', $limit, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return $this->revokeBatch($expired, UserSessionRevokedReason::EXPIRED_INACTIVITY);
    }

    /**
     * @return UserSessionInterface[]
     */
    public function markSessionsExceedingMaxDuration(int $maxDurationHours): array
    {
        $limit = new \DateTimeImmutable(\sprintf('-%d hours', $maxDurationHours), new \DateTimeZone('UTC'));

        $expired = $this->createQueryBuilder('s')
            ->where('s.createdAt < :limit')
            ->andWhere('s.active = :active')
            ->setParameter('active', true)
            ->setParameter('limit', $limit, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return $this->revokeBatch($expired, UserSessionRevokedReason::EXPIRED_MAX_DURATION);
    }

    /**
     * @param UserSessionInterface[] $sessions
     *
     * @return UserSessionInterface[]
     */
    private function revokeBatch(array $sessions, UserSessionRevokedReason $reason): array
    {
        if ([] === $sessions) {
            return [];
        }

        foreach ($sessions as $session) {
            $session->revoke($reason);
            $this->cache->deleteItem($this->cacheKey($session->getUserIdentifier()));
            $this->cache->deleteItem($this->activityLockKey($session->getSessionId()));
        }

        $this->getEntityManager()->flush();

        return $sessions;
    }

    /**
     * Source of truth for conflict detection — always reads the DB, never
     * the cache.
     */
    public function hasActiveSession(string $userIdentifier, string $currentSessionId): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.userIdentifier = :uid')
            ->andWhere('s.active = :active')
            ->andWhere('s.sessionId != :sid')
            ->setParameter('uid', $userIdentifier)
            ->setParameter('active', true)
            ->setParameter('sid', $currentSessionId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Not used for hasActiveSession() (see that method) — this one may
     * return a cached, slightly stale result, which is fine for a
     * non-critical read.
     */
    public function findActiveSession(string $userIdentifier): ?UserSessionInterface
    {
        $cacheKey = $this->cacheKey($userIdentifier);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $data = $item->get();
            /** @var UserSessionInterface|null $session */
            $session = $this->find($data['id']);

            if (null === $session || !$session->isActive()) {
                $this->cache->deleteItem($cacheKey);
            } else {
                return $session;
            }
        }

        $session = $this->findOneBy(
            ['userIdentifier' => $userIdentifier, 'active' => true],
            ['id' => 'DESC'],
        );

        if (null !== $session) {
            $this->cacheSession($userIdentifier, $session);
        }

        return $session;
    }

    /**
     * userIdentifier is very often an email address elsewhere in this
     * bundle — '@' is a reserved character under PSR-6
     * (CacheItemPoolInterface implementations must reject it), so the raw
     * identifier can never be used directly in a cache key.
     */
    private function cacheKey(string $userIdentifier): string
    {
        return self::CACHE_PREFIX.'_'.hash('xxh128', $userIdentifier);
    }

    private function activityLockKey(string $sessionId): string
    {
        return self::CACHE_PREFIX.'_activity_lock_'.hash('xxh128', $sessionId);
    }

    private function cacheSession(string $userIdentifier, UserSessionInterface $session): void
    {
        $item = $this->cache->getItem($this->cacheKey($userIdentifier));
        $item->set(['id' => $session->getId()]);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }
}