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

use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Domain\Permission\Model\AbstractUserPermissionRole;

/**
 * Doctrine mapping for a permission grant. Extend it in your application.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[ORM\MappedSuperclass]
abstract class UserPermissionRole extends AbstractUserPermissionRole
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'integer')]
    protected int|string|null $userId = null;

    #[ORM\ManyToOne(targetEntity: PermissionRole::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected $permissionRole = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    protected ?string $scope = null;

    #[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\UserInterface')]
    protected $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeInterface $deletedAt = null;

    #[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\UserInterface')]
    protected $deletedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $deletedReason = null;
}