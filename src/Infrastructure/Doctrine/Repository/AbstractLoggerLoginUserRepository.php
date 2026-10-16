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
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\LoggerLoginUserInterface;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\Persistance\LoggerLoginUserRepositoryInterface;
use Wlindabla\UserBundle\Infrastructure\Doctrine\AbstractLoggerLoginUser;

/**
 * Doctrine repository for user login records. 
*
* Responsibilities:
* - Persist new records (append-only)
* - Provide audit queries (by IP, by user, by time period)
* - Purge old logs according to a retention policy
*
 * @extends ServiceEntityRepository<AbstractLoggerLoginUser>
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractLoggerLoginUserRepository extends ServiceEntityRepository implements LoggerLoginUserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry,$entityClass);
    }

    public function save(LoggerLoginUserInterface $entity, bool $flush = true): LoggerLoginUserInterface
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $entity;
    }

    /** @return LoggerLoginUserInterface[] */
    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.email = :email')
            ->setParameter('email', $email)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return LoggerLoginUserInterface[] */
    public function findByUsername(string $username): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.username = :username')
            ->setParameter('username', $username)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return LoggerLoginUserInterface[] */
    public function findByIp(string $ip): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.lastLoginIp = :ip')
            ->setParameter('ip', $ip)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return LoggerLoginUserInterface[] */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByEmailSince(string $email, \DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.email = :email')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('email', $email)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByIpSince(string $ip, \DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.lastLoginIp = :ip')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastByEmail(string $email): ?LoggerLoginUserInterface
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.email = :email')
            ->setParameter('email', $email)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Purge physique (pas de SoftDeleteable sur cette entité).
     */
    public function purgeOlderThan(\DateTimeInterface $before): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->andWhere('l.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    public function createAuditQueryBuilder(string $alias = 'l'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->orderBy("{$alias}.createdAt", 'DESC');
    }
}