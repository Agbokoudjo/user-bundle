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

namespace Wlindabla\UserBundle\Domain\Permission\Model\Persistance;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface UserPermissionRoleRepositoryInterface
{
    /**
     * Whether $userId holds $permissionName through a direct grant (not
     * considering group-inherited grants — see
     * UserPermissionGroupRepositoryInterface::userHasPermissionViaGroup()).
     *
     * Assumes a soft-delete filter is active globally: a revoked
     * (soft-deleted) grant must never count. Implementations should not
     * add a manual deletedAt condition to avoid duplicating what that
     * filter already does.
     */
    public function userHasPermission(int|string $userId, string $permissionName): bool;

    /**
     * Counts ACTIVE (never revoked) assignments of a given permission —
     * typically used to block deleting a permission still granted to
     * someone.
     */
    public function countActiveAssignments(int $permissionRoleId): int;

    /**
     * IDs of every user ACTIVELY holding $permissionName through a direct
     * grant. Deliberately uncached, unlike userHasPermission(): intended
     * for occasional bulk reads (e.g. a notification fan-out), not a
     * per-request access check — freshness matters more than speed here.
     *
     * @return list<int|string>
     */
    public function findActiveHolderUserIds(string $permissionName): array;

    /**
     * Invalidates the permission cache. Call it after any change to a
     * UserPermissionRoleInterface row (grant, revoke).
     */
    public function invalidate(): void;
}