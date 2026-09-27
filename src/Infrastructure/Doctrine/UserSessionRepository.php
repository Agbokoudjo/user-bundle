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

namespace Wlindabla\UserBundle\Infrastructure\Doctrine;

use App\WlindablaUserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;
use App\Infrastructure\Doctrine\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Gestion des sessions utilisateur uniques.
 *
 * Stratégie : on ne supprime jamais (SoftDeleteable + audit),
 * on révoque (active = false) en traçant systématiquement la raison
 * (UserSessionRevokedReason) afin que SessionControlListener puisse
 * afficher le message adapté au navigateur concerné lors de sa
 * prochaine requête. Le cache ne sert qu'à éviter les lectures BDD
 * répétées, jamais comme source de vérité pour la détection de conflit.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
final class UserSessionRepository extends ServiceEntityRepository
{
    private const string CACHE_PREFIX = '_user_session';
    private const int CACHE_TTL    = 3600;
    private const int ACTIVITY_TTL = 3600; // 1 flush BDD max par heure

    public function __construct(
        ManagerRegistry $registry,
        #[Target('session.cache_pool')]
        private readonly CacheItemPoolInterface $cache 
    ) {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * Crée une session pour $userIdentifier.
     *
     * Toutes les sessions actives précédentes sont révoquées (raison CONFLICT)
     * AVANT la création, afin que l'appareil précédent détecte le conflit dès
     * sa prochaine requête (cf. SessionControlListener).
     */
    public function createSession(
        string $userIdentifier,
        string $sessionId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceFingerprint = null,
    ): UserSession {
        try {
            $session = new UserSession();
            $session->setUserIdentifier($userIdentifier);
            $session->setSessionId($sessionId);
            $session->setIpAddress($ipAddress);
            $session->setUserAgent($userAgent);
            $session->setDeviceFingerprint($deviceFingerprint);

            $em = $this->getEntityManager();
            $em->persist($session);
            $em->flush();

            $this->cacheSession($userIdentifier, $session);

            return $session;
        } catch (UniqueConstraintViolationException) {
            // Une requête concurrente a inséré ce sessionId entre notre check et notre insert.
            $existing = $this->findBySessionId($sessionId);
            if ($existing !== null) {
                return $existing;
            }
            throw new \RuntimeException("Conflit d'insertion sur la session {$sessionId}, introuvable après coup.");
        }
    }

    public function getSessionInfo(string $userIdentifier): ?array
    {
        return [];
    }

    /**
     * Récupère une session par son PHPSESSID, active OU révoquée.
     *
     * Contrairement à findActiveSession(), cette méthode ne filtre pas sur
     * active = true : c'est ce qui permet à SessionControlListener de
     * distinguer "cette session n'a jamais existé en BDD" (remember-me
     * après un logout propre → recréation silencieuse) de "cette session a
     * existé puis a été révoquée entre-temps" (conflit, inactivité, durée
     * max 24h → il faut forcer la reconnexion avec le bon message).
     *
     * Lecture directe BDD (pas de cache) : c'est justement l'état de
     * révocation qui nous intéresse ici, jamais périmé.
     */
    public function findBySessionId(string $sessionId): ?UserSession
    {
        return $this->findOneBy(['sessionId' => $sessionId]);
    }

    /**
     * Révoque en BDD toutes les sessions actives d'un utilisateur, en
     * traçant la raison de révocation.
     * Retourne les sessionId pour que l'appelant puisse nettoyer Redis.
     *
     * @return string[] sessionIds révoqués
     */
    public function revokeAllActiveSessions(
        string $userIdentifier,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::CONFLICT,
    ): array {
        $sessions = $this->findBy([
            'userIdentifier' => $userIdentifier,
            'active'         => true,
        ]);

        if (empty($sessions)) {
            return [];
        }

        $revokedSessionIds = [];

        foreach ($sessions as $session) {
            $session->revoke($reason);
            $this->cache->deleteItem('activity_lock_' . $session->getSessionId());
            $revokedSessionIds[] = $session->getSessionId();
        }

        $this->getEntityManager()->flush();
        $this->cache->deleteItem($this->cacheKey($userIdentifier));

        return $revokedSessionIds;
    }

    /**
     * Révoque une session précise par son PHPSESSID.
     * Appelé au logout (raison MANUAL_LOGOUT par défaut).
     */
    public function removeSession(
        string $sessionId,
        UserSessionRevokedReason $reason = UserSessionRevokedReason::MANUAL_LOGOUT,
    ): ?string {
        $session = $this->findOneBy(['sessionId' => $sessionId]);

        if ($session === null) {
            return null;
        }

        $session->revoke($reason);
        $this->getEntityManager()->flush();

        $this->cache->deleteItem($this->cacheKey($session->getUserIdentifier()));
        $this->cache->deleteItem('activity_lock_' . $sessionId);

        return $sessionId; // retourne l'id pour que l'appelant nettoie Redis
    }

    /**
     * Met à jour lastActivityAt — limité à 1 flush par heure via le cache.
     */
    public function updateSessionActivity(string $sessionId): void
    {
        $lockKey  = 'activity_lock_' . $sessionId;
        $lockItem = $this->cache->getItem($lockKey);

        if ($lockItem->isHit()) {
            return; // Déjà mis à jour dans cette fenêtre horaire
        }

        $session = $this->findOneBy([
            'sessionId' => $sessionId,
            'active'    => true,
        ]);

        if ($session === null) {
            return;
        }

        $session->updateActivity();
        $this->getEntityManager()->flush();

        $lockItem->set(true)->expiresAfter(self::ACTIVITY_TTL);
        $this->cache->save($lockItem);

        $this->cache->deleteItem($this->cacheKey($session->getUserIdentifier()));
    }

    /**
     * Révoque (raison EXPIRED_INACTIVITY) les sessions actives dont
     * lastActivityAt dépasse $hoursOfInactivity heures.
     *
     * @return UserSession[]
     */
    public function markExpiredSessionsAsInactive(int $hoursOfInactivity): array
    {
        $limit = new \DateTimeImmutable("-{$hoursOfInactivity} hours");

        /** @var UserSession[] $expired */
        $expired = $this->createQueryBuilder('s')
            ->where('s.lastActivityAt < :limit')
            ->andWhere('s.active = :active')
            ->setParameter('active', true)
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();

        return $this->revokeBatch($expired, UserSessionRevokedReason::EXPIRED_INACTIVITY);
    }

    /**
     * Révoque (raison EXPIRED_MAX_DURATION) les sessions actives dont
     * createdAt dépasse $maxDurationHours heures — durée absolue, même si
     * l'utilisateur est resté actif en continu. Aligné sur remember_me
     * (24h) : une session ne doit jamais survivre à son cookie.
     *
     * @return UserSession[]
     */
    public function markSessionsExceedingMaxDuration(int $maxDurationHours): array
    {
        $limit = new \DateTimeImmutable("-{$maxDurationHours} hours");

        /** @var UserSession[] $expired */
        $expired = $this->createQueryBuilder('s')
            ->where('s.createdAt < :limit')
            ->andWhere('s.active = :active')
            ->setParameter('active', true)
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();

        return $this->revokeBatch($expired, UserSessionRevokedReason::EXPIRED_MAX_DURATION);
    }

    /**
     * @param UserSession[] $sessions
     * @return UserSession[]
     */
    private function revokeBatch(array $sessions, UserSessionRevokedReason $reason): array
    {
        if (empty($sessions)) {
            return [];
        }

        foreach ($sessions as $session) {
            $session->revoke($reason);
            $this->cache->deleteItem($this->cacheKey($session->getUserIdentifier()));
            $this->cache->deleteItem('activity_lock_' . $session->getSessionId());
        }

        $this->getEntityManager()->flush();

        return $sessions;
    }

    /**
     * SOURCE DE VÉRITÉ pour la détection de conflit.
     *
     * Toujours lire la BDD pour cette vérification — jamais le cache.
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
     * Lecture de la session active — retourne null si aucune session active.
     * Utilise le cache pour éviter les lectures répétées (info non-critique).
     *
     * Ne PAS utiliser pour hasActiveSession (voir méthode ci-dessus).
     */
    public function findActiveSession(string $userIdentifier): ?UserSession
    {
        $cacheKey = $this->cacheKey($userIdentifier);
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $data    = $item->get();
            $session = $this->find($data['id']);
            if ($session === null || !$session->isActive()) {
                $this->cache->deleteItem($cacheKey);
            } else {
                return $session;
            }
        }

        $session = $this->findOneBy(
            ['userIdentifier' => $userIdentifier, 'active' => true],
            ['id' => 'DESC'],
        );

        if ($session !== null) {
            $this->cacheSession($userIdentifier, $session);
        }

        return $session;
    }

    private function cacheKey(string $userIdentifier): string
    {
        return self::CACHE_PREFIX . '_' . $userIdentifier;
    }

    private function cacheSession(string $userIdentifier, UserSession $session): void
    {
        $item = $this->cache->getItem($this->cacheKey($userIdentifier));
        $item->set(['id' => $session->getId()]);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }
}
