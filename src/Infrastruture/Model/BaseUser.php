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

namespace Wlindabla\UserBundle\Model\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Wlindabla\UserBundle\Infrastruture\Model\AbstractUser;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * Doctrine mapping for the base user. Extend this class in your application
 * (e.g. App\Entity\User) and declare it as the target of UserInterface via
 * doctrine.orm.resolve_target_entities.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[ORM\MappedSuperclass]
abstract class BaseUser extends AbstractUser
{
    #[Assert\NotBlank(message: 'Le nom complet est obligatoire.')]
    #[Assert\NotNull(message: 'Le nom complet ne peut pas être nul.')]
    #[Assert\Length(
        min: 6,
        max: 255,
        minMessage: 'Le nom complet doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom complet ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\p{M}\s\-\.]+$/u',
        message: 'Le nom complet ne peut contenir que des lettres (toutes langues), espaces, tirets, points',
    )]
    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected ?string $username = null;

    #[Assert\NotNull(message: 'L\'email ne peut pas être nul.')]
    #[Assert\Length(
        min: 6,
        max: 200,
        minMessage: 'L\'email doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Email(
        message: '{{ value }} n\'est pas une adresse email valide.',
        mode: 'html5',
    )]
    #[ORM\Column(type: "string", length: 200, unique: true)]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected ?string $email = null;

    #[ORM\Column(type: 'string')]
    protected ?string $password = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $enabled = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $emailVerified = false;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    protected array $roles = [];

     #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?\DateTimeInterface $passwordRequestedAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:cache', 'user:security','user:read'])]
    protected ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?string $confirmationToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?\DateTimeInterface $tokenRequestedAt = null;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?string $usernameCanonical = null;

    #[ORM\Column(type: 'string', length: 200, unique: true, nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?string $emailCanonical = null;

    #[Assert\File(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp','image/jpg'],
        mimeTypesMessage: 'Merci de fournir une image JPG, PNG ou WEBP valide.',
        maxSizeMessage: 'L\'image ne doit pas dépasser {{ limit }} {{ suffix }}.',
    )]
    #[Vich\UploadableField(mapping: 'avatars', fileNameProperty: 'avatarName')]
    protected ?File $avatarFile = null;

    /**
     * Timestamp pour Vich (obligatoire)
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:cache', 'user:read'])]
    protected ?\DateTimeInterface $avatarUpdatedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:cache', 'user:read'])]
    protected ?string $avatarName = null;

    #[Assert\NotBlank()]
    #[Assert\Length(min: 8, max: 80)]
    #[Assert\NotNull]
    #[ORM\Column(type: 'phone_number',length:80,nullable: true)]
    #[Groups(['user:read'])]
    protected ?PhoneNumber $phone = null;


    /**
     * Version normalisée (Unicode, sans accents/ponctuation, minuscule) de
     * $username, recalculée automatiquement à chaque persist/update par
     * SonataUserDoctrineLifecycleListener. Indexée en base (index GIN
     * pg_trgm) pour la détection de doublons.
     */
    #[ORM\Column(name: 'identity_normalized', type: 'string', nullable: true)]
    protected string $identityNormalized = '';

    public function getIdentityNormalized(): string
    {
        return $this->identityNormalized;
    }

    public function setIdentityNormalized(string $identityNormalized): void
    {
        $this->identityNormalized = $identityNormalized;
    }

    private function normalizeApostrophes(string $value): string
    {
        return str_replace(["’", '`'], "'", $value);
    }


    /**
     * Get the value of phone
     */
    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    /**
     * Set the value of phone
     */
    public function setPhone(?PhoneNumber $phone): void
    {
        $this->phone = $phone;
    }


    public function getAvatarFile(): ?File
    {
        return $this->avatarFile;
    }

    public function setAvatarFile(?File $avatarFile=null): void
    {
        $this->avatarFile = $avatarFile;

        if (null !== $avatarFile) {
            $this->avatarUpdatedAt = new \DateTime('now',new \DateTimeZone('UTC'));
        }
    }

    public function getAvatarName(): ?string
    {
        return $this->avatarName;
    }

    public function setAvatarName(?string $avatarName): void
    {
        $this->avatarName = $avatarName;
    }

    public function getAvatarUpdatedAt(): ?\DateTimeInterface
    {
        return $this->avatarUpdatedAt;
    }

    public function setAvatarUpdatedAt(?\DateTimeInterface $avatarUpdatedAt): void
    {
        $this->avatarUpdatedAt = $avatarUpdatedAt;
    }

}