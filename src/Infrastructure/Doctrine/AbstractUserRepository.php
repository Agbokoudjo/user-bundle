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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Wlindabla\ShareServiceBundle\Similarity\IdentityNormalizer;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserFindDuplicatesRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserRepositoryInterface;

/**
 * Base Doctrine repository for the user entity.
 *
 * Extend it in your application, on your own User entity:
 *
 *   class UserRepository extends AbstractUserRepository
 *   {
 *       public function __construct(ManagerRegistry $registry, TagAwareCacheInterface $cache)
 *       {
 *           parent::__construct($registry, User::class, $cache);
 *       }
 *   }
 *
 * Two groups of methods here have an extra requirement beyond doctrine/orm,
 * documented on each method:
 *
 * 1. findSimilarCandidates() / hasSuspectedDuplicate() — PostgreSQL only
 *    (pg_trgm). A project on another RDBMS must override
 *    findSimilarCandidates() in its own repository, or implement
 *    UserFindDuplicatesRepositoryInterface directly instead of extending
 *    this class.
 *
 * 2. createQueryBuilderForEnabledUsersExcludingRoles() /
 *    findByUserWithExcludRole() / findActiveEmailsByRole() /
 *    findActiveSuperAdminEmails() — require the roles column to be a
 *    PostgreSQL json/jsonb column and the JSONB_EXISTS DQL function to be
 *    registered, via:
 *
 *      composer require scienta/doctrine-json-functions
 *
 *      # config/packages/doctrine.yaml
 *      doctrine:
 *          orm:
 *              dql:
 *                  string_functions:
 *                      JSONB_EXISTS: Scienta\DoctrineJsonFunctions\Query\AST\Functions\Postgresql\JsonbExists
 *
 *    Check the exact class name shipped by the installed version of
 *    scienta/doctrine-json-functions before copying this line — it was not
 *    possible to confirm it here.
 *
 *    A project on another RDBMS, or that doesn't need these role-based
 *    finders, should not call them; they are not required by
 *    UserFindDuplicatesRepositoryInterface.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractUserRepository extends ServiceEntityRepository 
implements UserFindDuplicatesRepositoryInterface,UserRepositoryInterface
{
    /**
     * @param class-string $entityClass FQCN of your concrete User entity
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        string $entityClass,
        private readonly TagAwareCacheInterface $tagAwareCache,
    ) {
        parent::__construct($managerRegistry, $entityClass);
    }

    // -----------------------------------------------------------------
    // Duplicate-identity detection (PostgreSQL pg_trgm only)
    // -----------------------------------------------------------------

    /**
     * Pre-selects, via the pg_trgm GIN index, existing user accounts whose
     * username is closest to the submitted identity.
     *
     * PostgreSQL only:
     * - the `%` operator (the only one indexable by the pg_trgm GIN index)
     *   is used instead of calling similarity() directly in the WHERE;
     * - set_config() is used instead of SET LOCAL, so the threshold can be
     *   a bound parameter;
     * - the whole query runs inside an explicit transaction so that
     *   set_config(..., true) (LOCAL scope) reliably applies to the SELECT
     *   that follows, whatever the caller's transactional context.
     *
     * Not usable as-is on MySQL/MariaDB/SQLite: override this method in
     * your own repository for another RDBMS.
     *
     * @return array<int, array{id: int, username: string, identity_normalized: string, score: float}>
     */
    public function findSimilarCandidates(
        string $rawUsername,
        int $limit = 10,
        float $minTrigramScore = 0.3,
        bool $excludeDisabled = false,
    ): array {
        $normalized = IdentityNormalizer::normalize($rawUsername);

        if ('' === $normalized) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();

        $sql = \sprintf(
            'SELECT id, username, identity_normalized,
                    similarity(identity_normalized, :search) AS score
             FROM %s
             WHERE identity_normalized %% :search%s
             ORDER BY score DESC
             LIMIT :limit',
            $table,
            $excludeDisabled ? "\n  AND enabled = TRUE" : '',
        );

        return $connection->transactional(
            function (Connection $connection) use ($sql, $normalized, $limit, $minTrigramScore): array {
                $connection->executeStatement(
                    "SELECT set_config('pg_trgm.similarity_threshold', :threshold, true)",
                    ['threshold' => (string) $minTrigramScore],
                );

                return $connection->executeQuery(
                    $sql,
                    ['search' => $normalized, 'limit' => $limit],
                    ['search' => ParameterType::STRING, 'limit' => ParameterType::INTEGER],
                )->fetchAllAssociative();
            },
        );
    }

    public function hasSuspectedDuplicate(string $submittedUsername, int $candidateLimit = 10): bool
    {
        return [] !== $this->findSimilarCandidates($submittedUsername, $candidateLimit);
    }

    // -----------------------------------------------------------------
    // Role-based finders — require scienta/doctrine-json-functions
    // (see class-level PHPDoc)
    // -----------------------------------------------------------------

    /**
     * Builds the QueryBuilder for enabled users having NONE of the given
     * roles.
     *
     * Requires the JSONB_EXISTS DQL function — see the class-level PHPDoc
     * for the composer package and configuration needed.
     *
     * The default excluded roles (ROLE_FOUNDER, ROLE_SUPER_ADMIN) are this
     * repository's defaults, not a Symfony convention: pass your own list
     * if your application uses different role names.
     *
     * @param array<string> $exclude_roles roles to exclude
     */
    protected function createQueryBuilderForEnabledUsersExcludingRoles(
        array $exclude_roles = ['ROLE_FOUNDER', 'ROLE_SUPER_ADMIN'],
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('u')
            ->where('u.enabled = :enabled')
            ->setParameter('enabled', true);

        $andConditions = $qb->expr()->andX();

        foreach ($exclude_roles as $index => $role) {
            $paramName = 'excluded_role_'.(string) $index;
            $andConditions->add(\sprintf('JSONB_EXISTS(u.roles, :%s) = false', $paramName));
            $qb->setParameter($paramName, $role);
        }

        $qb->andWhere($andConditions);

        return $qb;
    }

    /**
     * Enabled users having none of the given roles, formatted for a form
     * field: ['username' => id].
     *
     * Requires the JSONB_EXISTS DQL function — see the class-level PHPDoc.
     * Result is cached under the 'admin_user_cache' tag; call
     * invalidateCache() whenever a user's roles or enabled status change.
     *
     * @param array<string> $exclude_roles roles to exclude
     *
     * @return array<string, int> [username => id]
     */
    public function findByUserWithExcludRole(array $exclude_roles = ['ROLE_FOUNDER', 'ROLE_SUPER_ADMIN']): array
    {
        $cacheKey = 'admin_user_'.implode('_', $exclude_roles);

        return $this->tagAwareCache->get($cacheKey, function (ItemInterface $item) use ($exclude_roles): array {
            $item->tag(['admin_user_cache']);

            $users = $this->createQueryBuilderForEnabledUsersExcludingRoles($exclude_roles)
                ->getQuery()
                ->getResult();

            $userChoices = [];

            /** @var BaseUserInterface $user */
            foreach ($users as $user) {
                $userChoices[$user->getUsername()] = $user->getId();
            }

            return $userChoices;
        });
    }

    /**
     * Emails of enabled accounts holding ROLE_SUPER_ADMIN.
     *
     * Requires the JSONB_EXISTS DQL function — see the class-level PHPDoc.
     * ROLE_SUPER_ADMIN is this repository's default role name; override
     * findActiveEmailsByRole() directly if your application names it
     * differently.
     *
     * @return string[]
     */
    public function findActiveSuperAdminEmails(): array
    {
        return $this->findActiveEmailsByRole('ROLE_SUPER_ADMIN');
    }

    /**
     * Emails of enabled accounts holding $role, with an optional
     * account-age filter.
     *
     * Requires the JSONB_EXISTS DQL function — see the class-level PHPDoc.
     *
     * @return string[]
     */
    public function findActiveEmailsByRole(string $role, ?\DateTimeImmutable $createdBefore = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u.email')
            ->where('u.enabled = :enabled')
            ->andWhere('JSONB_EXISTS(u.roles, :role) = true')
            ->setParameter('enabled', true)
            ->setParameter('role', $role);

        if (null !== $createdBefore) {
            $qb->andWhere('u.createdAt <= :createdBefore')
                ->setParameter('createdBefore', $createdBefore);
        }

        return array_column($qb->getQuery()->getScalarResult(), 'email');
    }

    // -----------------------------------------------------------------
    // Plain finder — no extra dependency
    // -----------------------------------------------------------------

    /**
     * Resolves the emails of a batch of enabled account IDs.
     *
     * @param int[] $ids
     *
     * @return string[]
     */
    public function findEmailsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $qb = $this->createQueryBuilder('u')
            ->select('u.email')
            ->where('u.id IN (:ids)')
            ->andWhere('u.enabled = :enabled')
            ->setParameter('ids', $ids)
            ->setParameter('enabled', true);

        return array_column($qb->getQuery()->getScalarResult(), 'email');
    }

    // -----------------------------------------------------------------
    // Cache
    // -----------------------------------------------------------------

    /**
     * Invalidates the 'admin_user_cache' tag used by
     * findByUserWithExcludRole(). Requires a TagAwareCacheInterface
     * (symfony/cache) to be injected.
     */
    public function invalidateCache(): void
    {
        try {
            $this->tagAwareCache->invalidateTags(['admin_user_cache']);
        } catch (\InvalidArgumentException) {
            // Swallowed on purpose: a cache invalidation failure must never
            // block the application. Worth logging if this ever fires.
        }
    }
}