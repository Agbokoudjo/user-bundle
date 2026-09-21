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

namespace Wlindabla\UserBundle\Domain\User\Application\Manager;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

interface UserManagerInterface 
{
    public function updatePassword(BaseUserInterface $user): void;

    public function findUserByUsername(string $username): ?BaseUserInterface;

    public function findUserByEmail(string $email): ?BaseUserInterface;

    public function findUserByUsernameOrEmail(string $usernameOrEmail): ?BaseUserInterface;

    public function findUserByConfirmationToken(string $token): ?BaseUserInterface;

    public function findUserBySlug(string $slug): ?BaseUserInterface;


    /**
     * Save an Entity.
     * @param BaseUserInterface $entity   The Entity to save
     * @param bool   $andFlush Flush the EntityManager after saving the object?
     */
    public function save(BaseUserInterface $entity, bool $andFlush = true): void;

    /**
     * Return the Entity class name.
     *
     * @phpstan-return class-string<BaseUserInterface>
     */
    public function getClass(): string;

    /**
     * Find all entities in the repository.
     *
     * @return BaseUserInterface[]
     *
     * @phpstan-return BaseUserInterface[]
     */
    public function findAll(): array;

    /**
     * Find entities by a set of criteria.
     *
     * @param array<string, mixed>                          $criteria
     * @param array<string, 'asc'|'ASC'|'desc'|'DESC'>|null $orderBy
     *
     * @return BaseUserInterface[]
     *
     * @phpstan-return BaseUserInterface[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * Find a single entity by a set of criteria.
     *
     * @param array<string, mixed> $criteria
     *
     * @phpstan-return BaseUserInterface|null
     */
    public function findOneBy(array $criteria): ?BaseUserInterface;

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @phpstan-return BaseUserInterface|null
     */
    public function find(mixed $id): ?BaseUserInterface;

    /**
     * Create an empty Entity instance.
     *
     * @phpstan-return BaseUserInterface
     */
    public function create(): BaseUserInterface;

    /**
     * Delete an Entity.
     *
     * @param object $entity   The Entity to delete
     * @param bool   $andFlush Flush the EntityManager after deleting the object?
     *
     * @phpstan-param BaseUserInterface $entity
     */
    public function delete(BaseUserInterface $entity, bool $andFlush = true): void;
}
