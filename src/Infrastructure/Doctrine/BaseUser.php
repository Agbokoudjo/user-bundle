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
use libphonenumber\PhoneNumber;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Wlindabla\UserBundle\Domain\User\Model\AbstractBaseUser;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SymfonySecurityUserInterface ;

/**
 * Doctrine mapping for the base user. Extend this class in your application
 * (e.g. App\Entity\User) and declare it as the target of UserInterface via
 * doctrine.orm.resolve_target_entities.
 * Framework-agnostic base implementation of a user.
 *
 * Persistence mapping is added by the Doctrine layer, not here.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[ORM\MappedSuperclass]
abstract class BaseUser extends AbstractBaseUser implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue('IDENTITY')]
    #[ORM\Column(type: "integer")]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected int|string|null $id = null;
    
    #[Assert\NotBlank(message: 'Full name is required.')]
    #[Assert\NotNull(message: 'Full name cannot be null.')]
    #[Assert\Length(
        min: 6,
        max: 255,
        minMessage: 'Full name must contain at least {{ limit }} characters.',
        maxMessage: 'Full name cannot exceed {{ limit }} characters.',
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\p{M}\s\-\.]+$/u',
        message: 'Full name can only contain letters (all languages), spaces, hyphens, and dots.',
    )]
    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected ?string $username = null;

    #[Assert\NotNull(message: 'Email cannot be null.')]
    #[Assert\Length(
        min: 6,
        max: 200,
        minMessage: 'Email must contain at least {{ limit }} characters.',
        maxMessage: 'Email cannot exceed {{ limit }} characters.',
    )]
    #[Assert\Email(
        message: '{{ value }} is not a valid email address.',
        mode: 'html5',
    )]
    #[ORM\Column(type: "string", length: 200, unique: true)]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected ?string $email = null;

    #[ORM\Column(type: 'string')]
    protected ?string $password = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected bool $enabled = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected bool $emailVerified = false;

    /** @var list<string> */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
    protected array $roles = [];

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?\DateTimeInterface $passwordRequestedAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:cache', 'user:security', 'user:read'])]
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
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'],
        mimeTypesMessage: 'Please upload a valid JPG, PNG, or WEBP image.',
        maxSizeMessage: 'The image size cannot exceed {{ limit }} {{ suffix }}.',
    )]
    #[Vich\UploadableField(mapping: 'avatars', fileNameProperty: 'avatarName')]
    protected ?File $avatarFile = null;

    /**
     * Timestamp for Vich (required)
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
    #[ORM\Column(type: 'phone_number', length: 80, nullable: true)]
    #[Groups(['user:read'])]
    protected ?PhoneNumber $phone = null;

    /**
     * Timestamp of the last successful MFA code verification — set exclusively by
     * TwoFactorAuthController::verify(). Intentionally attached to the ACCOUNT rather
     * than UserSession: a remember-me re-authentication creates a new UserSession record
     * every time (new PHP session ID), which would reset MFA freshness on every PHP session
     * restart if stored there. Here, it persists across cookie/session changes.
     */
    #[Groups(['user:cache', 'user:security'])]
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $twoFactorValidatedAt = null;

    /**
     * Admin activity heartbeat — updated (throttled, see TwoFactorAuthListener) on
     * every admin request that passes the MFA check. Used to measure sliding INACTIVITY
     * rather than a fixed clock since login: a continuously active session will never require
     * a new code, while a session idle for over 2 hours will prompt for one.
     */
    #[Groups(['user:cache', 'user:security'])]
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $twoFactorLastCheckedAt = null;

    /**
     * Normalized version (Unicode, without accents/punctuation, lowercase) of $username,
     * automatically recalculated on every persist/update by SonataUserDoctrineLifecycleListener.
     * Indexed in database (pg_trgm GIN index) for duplicate detection.
     */
    #[ORM\Column(name: 'identity_normalized', type: 'string', nullable: true)]
    protected string $identityNormalized = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:cache', 'user:security'])]
    protected ?string $salt = null;

    public function getIdentityNormalized(): string
    {
        return $this->identityNormalized;
    }

    public function setSalt(?string $salt): void
    {
        $this->salt = $salt;
    }
    
    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setIdentityNormalized(string $identityNormalized): void
    {
        $this->identityNormalized = $identityNormalized;
    }

    protected function normalizeApostrophes(string $value): string
    {
        return str_replace(["’", '`'], "'", $value);
    }

    public function isEqualTo(SymfonySecurityUserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        if ($this->password !== $user->getPassword()) {
            return false;
        }

        if ($this->getUserIdentifier() !== $user->getUserIdentifier()) {
            return false;
        }

        return true;
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

    public function setAvatarFile(?File $avatarFile = null): void
    {
        $this->avatarFile = $avatarFile;

        if (null !== $avatarFile) {
            $this->avatarUpdatedAt = new \DateTime('now', new \DateTimeZone('UTC'));
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

    /**
     * Sets both timestamp fields to "now" (UTC) in a single operation — called
     * exclusively by TwoFactorAuthController::verify() upon successful code validation.
     */
    public function markTwoFactorValidated(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->twoFactorValidatedAt = $now;
        $this->twoFactorLastCheckedAt = $now;
    }

    public function getTwoFactorValidatedAt(): ?\DateTimeInterface { return $this->twoFactorValidatedAt; }
    public function setTwoFactorValidatedAt(?\DateTimeInterface $twoFactorValidatedAt): void { $this->twoFactorValidatedAt = $twoFactorValidatedAt; }
    public function getTwoFactorLastCheckedAt(): ?\DateTimeInterface { return $this->twoFactorLastCheckedAt; }
    public function setTwoFactorLastCheckedAt(?\DateTimeInterface $twoFactorLastCheckedAt): void { $this->twoFactorLastCheckedAt = $twoFactorLastCheckedAt; }
}