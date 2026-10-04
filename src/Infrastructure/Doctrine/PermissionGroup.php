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

namespace Wlindabla\UserBundle\Infrastructure\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Domain\Permission\Model\AbstractPermissionGroup;
use Wlindabla\UserBundle\Domain\Permission\Model\PermissionRoleInterface;

/**
 * Doctrine mapping for a permission group. Extend it in your application.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[ORM\MappedSuperclass]
abstract class PermissionGroup extends AbstractPermissionGroup
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50, unique: true)]
    protected ?string $code = null;

    #[ORM\Column(type: 'string', length: 150)]
    protected ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    /** @var Collection<int, PermissionRoleInterface> */
    #[ORM\ManyToMany(targetEntity: PermissionRoleInterface::class)]
    #[ORM\JoinTable(name: 'permission_group_permission_role')]
    protected Collection $permissions;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
    }

    public function getPermissions(): iterable
    {
        return $this->permissions;
    }

    public function addPermission(PermissionRoleInterface $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }

        return $this;
    }

    public function removePermission(PermissionRoleInterface $permission): static
    {
        $this->permissions->removeElement($permission);

        return $this;
    }

    public function clearPermissions(): static
    {
        $this->permissions->clear();

        return $this;
    }
}