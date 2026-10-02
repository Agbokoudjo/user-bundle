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

namespace Wlindabla\UserBundle\Infrastructure\Security\Authorization;

use Wlindabla\UserBundle\Domain\Permission\Application\Security\AuthorizationCheckerForUserInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\Persistance\UserPermissionGroupRepositoryInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\Persistance\UserPermissionRoleRepositoryInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class AuthorizationCheckerForUser implements AuthorizationCheckerForUserInterface
{
    public function __construct(
        private readonly UserPermissionRoleRepositoryInterface $userPermissionRoleRepository,
        private readonly UserPermissionGroupRepositoryInterface $userPermissionGroupRepository,
    ) {
    }

    public function isGrantedForUser(BaseUserInterface $user, string $permission): bool
    {
        if ($user->hasRole($permission)) {
            return true;
        }

        $userId = $user->getId();

        if (null === $userId) {
            return false;
        }

        return $this->userPermissionRoleRepository->userHasPermission($userId, $permission)
            || $this->userPermissionGroupRepository->userHasPermissionViaGroup($userId, $permission);
    }

    public function isMemberOfGroupForUser(BaseUserInterface $user, string $groupCode): bool
    {
        $userId = $user->getId();

        return null !== $userId && $this->userPermissionGroupRepository->userHasGroup($userId, $groupCode);
    }
}