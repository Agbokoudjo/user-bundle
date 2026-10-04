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
use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeRequestStatus;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\Persistance\EmailChangeRequestRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Extend it in your application:
 *
 *   class EmailChangeRequestRepository extends AbstractEmailChangeRequestRepository
 *   {
 *       public function __construct(ManagerRegistry $registry)
 *       {
 *           parent::__construct($registry, EmailChangeRequest::class);
 *       }
 *   }
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractEmailChangeRequestRepository extends ServiceEntityRepository implements EmailChangeRequestRepositoryInterface
{
    /** Statuses considered "in progress" — block a new request for the same account. */
    private const array ACTIVE_STATUSES = [
        EmailChangeRequestStatus::Pending,
        EmailChangeRequestStatus::AwaitingValidation,
    ];

    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    public function save(EmailChangeRequestInterface $request, bool $flush = true): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($request);

        if ($flush) {
            $entityManager->flush();
        }
    }

    public function findActiveForTargetUser(BaseUserInterface $targetUser): ?EmailChangeRequestInterface
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.targetUser = :targetUser')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('targetUser', $targetUser)
            ->setParameter('activeStatuses', self::ACTIVE_STATUSES)
            ->orderBy('r.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return EmailChangeRequestInterface[]
     */
    public function findExpiredButStillActive(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:activeStatuses)')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('activeStatuses', self::ACTIVE_STATUSES)
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EmailChangeRequestInterface[]
     */
    public function findHistoryForTargetUser(BaseUserInterface $targetUser): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.targetUser = :targetUser')
            ->setParameter('targetUser', $targetUser)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}