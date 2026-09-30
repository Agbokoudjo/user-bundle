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

namespace Wlindabla\UserBundle\Domain\User\Model\Persistance;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Generic, storage-agnostic read contract for a repository of T. Framework
 * and Doctrine-agnostic on purpose — implemented by
 * Infrastructure\Doctrine\Repository\AbstractUserRepository, but usable by
 * any service that only needs read access (no persist/flush), without
 * pulling in a full EntityManagerInterface dependency.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 *
 * @phpstan-template T of object
 */
interface UserRepositoryInterface
{
    /**
     * @phpstan-return class-string<BaseUserInterface>
     */
    public function getClass(): string;

    /**
     * @return object[]
     *
     * @phpstan-return BaseUserInterface[]
     */
    public function findAll(): array;

    /**
     * @param array<string, mixed>                           $criteria
     * @param array<string, 'asc'|'ASC'|'desc'|'DESC'>|null $orderBy
     *
     * @return BaseUserInterface[]
     *
     * @phpstan-return BaseUserInterface[]
     */
    public function findBy(
        array $criteria, 
        ?array $orderBy = null, 
        ?int $limit = null, 
        ?int $offset = null): array;

    /**
     * @param array<string, mixed> $criteria
     *
     * @phpstan-return BaseUserInterface|null
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * @phpstan-return BaseUserInterface|null
     */
    public function find(mixed $id): ?object;

    public function save(BaseUserInterface $entity, bool $andFlush = true): void ;

    public function delete(BaseUserInterface $entity, bool $andFlush = true): void ;

    public function getReference(int|string $id): BaseUserInterface ;

    public function findOneByUsernameOrEmail(string $usernameCanonical,string $emailCanonical): ?BaseUserInterface ;
}