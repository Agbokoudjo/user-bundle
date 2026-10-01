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
     * Le code PENDING le plus récent d'un utilisateur (borne non expirée
     * exclue volontairement : c'est au manager/domaine de trancher via
     * isPending(), pour garder la règle métier à un seul endroit).
     */
    public function findLatestPending(string $userIdentifier): ?TwoFactorCodeInterface
    {
        return $this->findOneBy(
            ['userIdentifier' => $userIdentifier, 'status' => TwoFactorCodeStatus::PENDING],
            ['id' => 'DESC'],
        );
    }

    /**
     * Nombre de codes envoyés depuis $since (fenêtre de limitation de débit,
     * 3 envois / 15 min) — compte les lignes créées, PENDING ou non.
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
     * Invalide (EXPIRED) tous les codes PENDING d'un utilisateur — appelé
     * avant de générer un nouveau code, et à la vérification réussie pour
     * empêcher la réutilisation d'un code plus ancien encore valide.
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
