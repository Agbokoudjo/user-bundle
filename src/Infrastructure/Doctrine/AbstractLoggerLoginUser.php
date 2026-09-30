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
use Doctrine\ORM\Mapping\MappedSuperclass;
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\BaseLoggerLoginUser;

/**
 * Entité Doctrine représentant un enregistrement de connexion utilisateur.
 *
 * Cette entité est intentionnellement en LECTURE SEULE après persistance :
 * les logs de connexion constituent un journal d'audit immuable.
 *
 * createdAt/updatedAt sont gérés par TimestampableEntity + DoctrineTimestampableListener
 * (prePersist/preUpdate génériques), aucune écriture manuelle nécessaire.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[MappedSuperclass]
abstract class AbstractLoggerLoginUser extends BaseLoggerLoginUser
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 200)]
    protected string $username;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $email;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    protected ?string $lastLoginIp = null;

    #[ORM\Column(type: 'text', length: 1000, nullable: true)]
    protected ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $userAgentLabel = null;
    
     #[ORM\Column(type: "datetime_immutable")]
    protected ?\DateTimeInterface $createdAt = null;

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt=null): static
    {
        $this->createdAt = $createdAt;

        return $this ;
    }

    public function prePersistTimestampable(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
        }
    }

    public function __construct() {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return \sprintf('%s <%s>', $this->username, $this->email);
    }
}