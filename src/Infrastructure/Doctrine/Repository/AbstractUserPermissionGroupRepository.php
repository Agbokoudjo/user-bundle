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
use Wlindabla\UserBundle\Domain\Permission\Model\Persistance\UserPermissionGroupRepositoryInterface;

/**
 * Extend it in your application:
 *
 *   class UserPermissionGroupRepository extends AbstractUserPermissionGroupRepository
 *   {
 *       public function __construct(ManagerRegistry $registry, TagAwareCacheInterface $cache, LoggerInterface $logger)
 *       {
 *           parent::__construct($registry, UserPermissionGroup::class, $cache, $logger);
 *       }
 *   }
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractUserPermissionGroupRepository extends ServiceEntityRepository implements UserPermissionGroupRepositoryInterface
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

    /**
     * Same principle as AbstractUserPermissionRoleRepository::
     * userHasPermission(), but through a granted GROUP rather than a
     * direct individual grant — see UserPermissionGroupInterface's own
     * docblock ("both tables must be queried").
     */
    public function userHasPermissionViaGroup(int|string $userId, string $permissionName): bool
    {
        return $this->permissionCache->get(
            \sprintf('user_permission_group.%s.%s', $userId, $permissionName),
            function (ItemInterface $item) use ($userId, $permissionName): bool {
                $item->tag([self::CACHE_TAG]);

                $count = $this->createQueryBuilder('upg')
                    ->select('COUNT(upg.id)')
                    ->innerJoin('upg.permissionGroup', 'pg')
                    ->innerJoin('pg.permissions', 'pr')
                    ->andWhere('upg.userId = :userId')
                    ->andWhere('pr.name = :name')
                    ->setParameter('userId', $userId)
                    ->setParameter('name', $permissionName)
                    ->getQuery()
                    ->getSingleScalarResult();

                return (int) $count > 0;
            },
        );
    }

    /**
     * Direct membership in a permission group, by its code.
     *
     * IDENTITY() reads the foreign key column directly. Since
     * permission_group_code references PermissionGroup.code, that value
     * IS the code — no join needed.
     */
    public function userHasGroup(int|string $userId, string $groupCode): bool
    {
        return $this->permissionCache->get(
            \sprintf('user_group_membership.%s.%s', $userId, $groupCode),
            function (ItemInterface $item) use ($userId, $groupCode): bool {
                $item->tag([self::CACHE_TAG]);

                $count = $this->createQueryBuilder('upg')
                    ->select('COUNT(upg.id)')
                    ->andWhere('upg.userId = :userId')
                    ->andWhere('IDENTITY(upg.permissionGroup) = :groupCode')
                    ->setParameter('userId', $userId)
                    ->setParameter('groupCode', $groupCode)
                    ->getQuery()
                    ->getSingleScalarResult();

                return (int) $count > 0;
            },
        );
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