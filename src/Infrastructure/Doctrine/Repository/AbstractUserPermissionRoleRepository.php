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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\Persistance\UserPermissionRoleRepositoryInterface;

/**
 * Extend it in your application:
 *
 *   class UserPermissionRoleRepository extends AbstractUserPermissionRoleRepository
 *   {
 *       public function __construct(ManagerRegistry $registry, TagAwareCacheInterface $cache, LoggerInterface $logger)
 *       {
 *           parent::__construct($registry, UserPermissionRole::class, $cache, $logger);
 *       }
 *   }
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractUserPermissionRoleRepository extends ServiceEntityRepository implements UserPermissionRoleRepositoryInterface
{
    private const string CACHE_TAG = 'wlindabla_user_permissions';

    public function __construct(
        ManagerRegistry $registry,
        string $entityClass,
        private readonly TagAwareCacheInterface $permissionCache,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, $entityClass);
    }

    public function userHasPermission(int|string $userId, string $permissionName): bool
    {
        return $this->permissionCache->get(
            \sprintf('user_permission_role.%s.%s', $userId, $permissionName),
            function (ItemInterface $item) use ($userId, $permissionName): bool {
                $item->tag([self::CACHE_TAG]);

                $count = $this->createQueryBuilder('upr')
                    ->select('COUNT(upr.id)')
                    ->innerJoin('upr.permissionRole', 'pr')
                    ->andWhere('upr.userId = :userId')
                    ->andWhere('pr.name = :name')
                    ->setParameter('userId', $userId)
                    ->setParameter('name', $permissionName)
                    ->getQuery()
                    ->getSingleScalarResult();

                return (int) $count > 0;
            },
        );
    }

    public function countActiveAssignments(int $permissionRoleId): int
    {
        return (int) $this->createQueryBuilder('upr')
            ->select('COUNT(upr.id)')
            ->andWhere('upr.permissionRole = :permissionRoleId')
            ->setParameter('permissionRoleId', $permissionRoleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveHolderUserIds(string $permissionName): array
    {
        $rows = $this->createQueryBuilder('upr')
            ->select('upr.userId AS userId')
            ->innerJoin('upr.permissionRole', 'pr')
            ->andWhere('pr.name = :name')
            ->setParameter('name', $permissionName)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'userId');
    }

    public function invalidate(): void
    {
        try {
            $this->permissionCache->invalidateTags([self::CACHE_TAG]);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to invalidate the permission cache.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}