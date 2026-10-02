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
 * A single business-level permission, independent of Symfony's technical
 * roles (ROLE_ADMIN and the like, carried on BaseUserInterface::getRoles()).
 *
 * Deliberately NOT called a "role" anywhere in this module's own
 * vocabulary — a project may freely mix the two concepts (e.g. require
 * both ROLE_ADMIN and a PermissionRole to perform an action), and keeping
 * the names distinct avoids ambiguity about which system a given check is
 * about.
 *
 * Example (illustrative only — the actual catalog is entirely owned by
 * the consuming application, this bundle ships no predefined permission):
 * a project could create a permission named "invoices:approve", with
 * context "billing", then grant it to specific users via
 * UserPermissionRoleInterface — without touching this bundle's code or
 * its security.yaml.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface PermissionRoleInterface
{
    public function getId(): int|string;

    /**
     * Stable, unique identifier for this permission (e.g.
     * "invoices:approve"). Used everywhere this permission is referenced
     * — by UserPermissionRoleInterface, by a Voter, by application code —
     * never the numeric ID, which only exists for the database relation.
     */
    public function getName(): ?string;

    public function setName(string $name): static;

    /**
     * Human-readable explanation of what this permission grants. Shown to
     * whoever assigns permissions (typically an administrator), not to
     * the end user the permission applies to.
     */
    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    /**
     * Free-form grouping label (e.g. "billing", "reporting"). Entirely
     * optional and owned by the consuming application — this bundle
     * neither defines nor interprets any particular value.
     */
    public function getContext(): ?string;

    public function setContext(?string $context): static;
}