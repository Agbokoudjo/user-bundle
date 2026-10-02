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
 * Grants a single PermissionRoleInterface to a specific user, with an
 * optional scope.
 *
 * This is the record that materializes "this person has been granted
 * that permission" — typically created by an administrator through a UI,
 * without a developer touching code or security.yaml.
 *
 * userId is intentionally int|string (not a Doctrine relation to the
 * User entity) — see AbstractUserPermissionRole's own docblock for the
 * reasoning and the trade-off this implies.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface UserPermissionRoleInterface
{
    public function getId(): int|string|null;

    public function getUserId(): int|string|null;

    public function setUserId(int|string $userId): static;

    public function getPermissionRole(): ?PermissionRoleInterface;

    public function setPermissionRole(PermissionRoleInterface $permissionRole): static;

    /**
     * Optional scope narrowing where this grant applies (e.g.
     * "department=north") — entirely free-form, interpreted only by the
     * consuming application's own authorization logic, never by this
     * bundle.
     */
    public function getScope(): ?string;

    public function setScope(?string $scope): static;
}