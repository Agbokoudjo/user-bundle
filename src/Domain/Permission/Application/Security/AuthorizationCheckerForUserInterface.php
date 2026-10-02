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

namespace Wlindabla\UserBundle\Domain\Permission\Application\Security;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Single entry point for checking whether a user holds a given right,
 * whether that right is a Symfony technical role
 * (BaseUserInterface::hasRole()) OR a business permission granted via
 * PermissionRoleInterface/UserPermissionRoleInterface (directly, or
 * inherited through a PermissionGroupInterface).
 *
 * Application code (Voters included) should never query
 * UserPermissionRoleRepositoryInterface or
 * UserPermissionGroupRepositoryInterface directly — always through this
 * interface, so the two grant paths (direct and group-inherited) are
 * never checked inconsistently in different places.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface AuthorizationCheckerForUserInterface
{
    /**
     * True if $user holds $permission, either as a Symfony role
     * (hasRole()) or as a business permission — granted directly, or
     * inherited through a permission group.
     */
    public function isGrantedForUser(BaseUserInterface $user, string $permission): bool;

    /**
     * Direct membership in a permission group, by its code. Complements
     * isGrantedForUser(), which answers about a single PERMISSION, not a
     * GROUP.
     */
    public function isMemberOfGroupForUser(BaseUserInterface $user, string $groupCode): bool;
}