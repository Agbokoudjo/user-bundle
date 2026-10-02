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
 * Framework-agnostic base implementation of a permission group. Doctrine
 * mapping (including the ManyToMany to PermissionRoleInterface) is added
 * by the Infrastructure layer — see Infrastructure\Doctrine\PermissionGroup.
 *
 * Deliberately carries no audit/blame fields (createdBy, deletedAt...),
 * unlike AbstractPermissionRole — a permission group is treated as
 * relatively static catalog data here. A project that needs the same
 * audit trail on groups as on individual permissions can add
 * ManagedCatalogInterface to its own concrete subclass.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractPermissionGroup implements PermissionGroupInterface
{
    protected ?string $code = null;

    protected ?string $label = null;

    protected ?string $description = null;

    /** @var iterable<PermissionRoleInterface> */
    protected $permissions = [];

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function __toString(): string
    {
        return $this->label ?? $this->code ?? '';
    }
}