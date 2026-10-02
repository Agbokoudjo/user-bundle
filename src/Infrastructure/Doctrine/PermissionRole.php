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
use Wlindabla\UserBundle\Domain\Permission\Model\AbstractPermissionRole;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Doctrine mapping for a business permission. Extend it in your
 * application:
 *
 *   #[ORM\Entity]
 *   #[ORM\Table(name: 'permission_role')]
 *   class PermissionRole extends \Wlindabla\UserBundle\Infrastructure\Doctrine\PermissionRole {}
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[ORM\MappedSuperclass]
abstract class PermissionRole extends AbstractPermissionRole
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected int|string|null $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    protected ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    protected ?string $context = null;

    #[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\BaseUserInterface')]
    protected ?BaseUserInterface $createdBy = null;

    #[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\BaseUserInterface')]
    protected ?BaseUserInterface $currentManager = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeInterface $deletedAt = null;

    #[ORM\ManyToOne]
    protected $deletedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $deletedReason = null;
}