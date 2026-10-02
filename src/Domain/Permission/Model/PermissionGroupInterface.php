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
 * A named, reusable bundle of several PermissionRoleInterface instances.
 *
 * Problem this solves: without it, granting someone a whole profile's
 * worth of permissions means checking each PermissionRole individually —
 * tedious and error-prone once the catalog grows past a handful of
 * entries. With a PermissionGroup, an administrator picks ONE group and
 * grants every permission it contains at once.
 *
 * A PermissionRole may legitimately belong to more than one group (the
 * relation is many-to-many) — this bundle makes no assumption about how
 * groups and permissions are organized; that catalog is entirely owned by
 * the consuming application.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface PermissionGroupInterface
{
    /**
     * Stable, human-chosen identifier (e.g. "billing_manager") — this is
     * the primary key, not a numeric ID: a permission group is catalog
     * data, meant to be referenced by a readable name across environments
     * (dev/staging/prod) without relying on autoincrement ordering.
     */
    public function getCode(): ?string;

    public function setCode(string $code): static;

    public function getLabel(): ?string;

    public function setLabel(string $label): static;

    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    /**
     * @return iterable<PermissionRoleInterface>
     */
    public function getPermissions(): iterable;

    public function addPermission(PermissionRoleInterface $permission): static;

    public function removePermission(PermissionRoleInterface $permission): static;

    /**
     * Clears every permission from this group — typically used right
     * before re-importing a group's full permission list from an external
     * source, rather than diffing additions/removals one by one.
     */
    public function clearPermissions(): static;
}