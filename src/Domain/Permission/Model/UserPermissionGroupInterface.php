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

namespace Wlindabla\UserBundle\Domain\Permission\Model;

/**
 * Grants a whole PermissionGroupInterface to a specific user at once,
 * rather than each of its permissions individually via
 * UserPermissionRoleInterface.
 *
 * Does NOT write anything into the individual-grant table: granting a
 * group here creates no UserPermissionRoleInterface rows. Checking
 * whether a user holds a given permission must therefore consider BOTH
 * direct grants and group-inherited ones — that logic belongs in
 * AuthorizationCheckerForUserInterface, not here.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface UserPermissionGroupInterface
{
    public function getId(): int|string|null;

    public function getUserId(): int|string|null;

    public function setUserId(int|string $userId): static;

    public function getPermissionGroup(): ?PermissionGroupInterface;

    public function setPermissionGroup(PermissionGroupInterface $permissionGroup): static;

    public function getScope(): ?string;

    public function setScope(?string $scope): static;
}