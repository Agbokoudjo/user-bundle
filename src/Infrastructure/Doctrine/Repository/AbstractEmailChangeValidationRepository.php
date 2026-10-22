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
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractEmailChangeValidationRepository extends ServiceEntityRepository implements EmailChangeValidationInterface
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    public function save(EmailChangeValidationInterface $validation, bool $flush = true): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($validation);

        if ($flush) {
            $entityManager->flush();
        }
    }

    /**
     * @return EmailChangeValidationInterface[]
     */
    public function findByRequest(EmailChangeRequestInterface $request): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.emailChangeRequest = :request')
            ->setParameter('request', $request)
            ->orderBy('v.decidedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Complements the UNIQUE constraint on the concrete table: that
     * constraint is the real guarantee against concurrent double votes,
     * this check only turns it into a clear error message instead of a
     * raw SQL exception.
     */
    public function hasAlreadyVoted(EmailChangeRequestInterface $request, BaseUserInterface $validator): bool
    {
        $count = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.emailChangeRequest = :request')
            ->andWhere('v.validator = :validator')
            ->setParameter('request', $request)
            ->setParameter('validator', $validator)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}