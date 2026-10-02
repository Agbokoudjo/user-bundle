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
interface UserPermissionGroupRepositoryInterface
{
    /**
     * Same principle as UserPermissionRoleRepositoryInterface::
     * userHasPermission(), but through a granted GROUP rather than a
     * direct individual grant — see UserPermissionGroupInterface's own
     * docblock ("both tables must be queried").
     */
    public function userHasPermissionViaGroup(int|string $userId, string $permissionName): bool;

    /**
     * Direct membership in a permission group, by its code.
     *
     * Not to be confused with userHasPermissionViaGroup(): that one
     * answers "does this user hold THIS PERMISSION, possibly inherited
     * from a group", this one answers "does this user belong to THIS
     * GROUP".
     */
    public function userHasGroup(int|string $userId, string $groupCode): bool;

    public function invalidate(): void;
}