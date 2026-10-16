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
use Doctrine\Persistence\ManagerRegistry;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorCodeStatus;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\Persistance\TwoFactorCodeRepositoryInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;

/**
 * MFA code persistence. Extend it in your application on your own
 * TwoFactorCode entity.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractTwoFactorCodeRepository extends ServiceEntityRepository implements TwoFactorCodeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry,string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
    * A user's most recent PENDING code (non-expired entries are
    * intentionally excluded: it is up to the manager/domain to decide
    * via isPending(), in order to keep the business logic in a single place). 
    */
    public function findLatestPending(string $userIdentifier): ?TwoFactorCodeInterface
    {
        return $this->findOneBy(
            ['userIdentifier' => $userIdentifier, 'status' => TwoFactorCodeStatus::PENDING],
            ['id' => 'DESC'],
        );
    }

    /**
    * Number of codes sent since $since (rate-limiting window,
    * 3 sends / 15 min) — counts created rows, regardless of whether they are PENDING. 
    */
    public function countSentSince(string $userIdentifier, \DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.userIdentifier = :uid')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('uid', $userIdentifier)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(TwoFactorCodeInterface $code, bool $flush = true): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($code);

        if ($flush) {
            $entityManager->flush();
        }
    }

    /**
    * Invalidates (sets to EXPIRED) all PENDING codes for a user — called
    * before generating a new code, and upon successful verification,
    * to prevent the reuse of an older, still-valid code. 
    */
    public function invalidatePending(string $userIdentifier): void
    {
        $pendingCodes = $this->findBy([
            'userIdentifier' => $userIdentifier,
            'status' => TwoFactorCodeStatus::PENDING,
        ]);

        if ([] === $pendingCodes) {
            return;
        }

        foreach ($pendingCodes as $code) {
            $code->setStatus(TwoFactorCodeStatus::EXPIRED);
        }

        $this->getEntityManager()->flush();
    }

    public function getClass(): string
    {
        return $this->getClassName() ;
    }
}
