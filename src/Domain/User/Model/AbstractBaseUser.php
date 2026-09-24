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

namespace Wlindabla\UserBundle\Domain\User\Model;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Framework-agnostic base implementation of a user.
 *
 * Persistence mapping is added by the Doctrine layer, not here.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractBaseUser implements BaseUserInterface
{
    /** Role granted to every user. Override in your entity if needed (never ROLE_ADMIN). */
    public const string ROLE_DEFAULT = 'ROLE_USER';

    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    protected int|string|null $id = null;

    protected  ?string $username = null;

    protected ?string $usernameCanonical = null;

    protected ?string $email = null;

    protected ?string $emailCanonical = null;

    protected ?string $password = null;

    protected ?string $plainPassword = null;

    protected bool $enabled = false;

    protected bool $emailVerified = false;

     protected ?\DateTimeInterface $lastLogin = null;

    protected ?string $confirmationToken = null;

    protected ?string $slug = null;
    
    protected ?\DateTimeInterface $passwordRequestedAt = null;

    protected ?\DateTimeInterface  $tokenRequestedAt = null;

    protected ?\DateTimeImmutable $emailVerifiedAt = null;

    /** @var list<string> */
    protected array $roles = [];

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, static::ROLE_DEFAULT]));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = [];

        foreach ($roles as $role) {
            $this->addRole($role);
        }

        return $this;
    }

    public function addRole(string $role): static
    {
        $role = strtoupper($role);

        if (static::ROLE_DEFAULT !== $role && !\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole(string $role): static
    {
        $key = array_search(strtoupper($role), $this->roles, true);

        if (false !== $key) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return \in_array(strtoupper($role), $this->getRoles(), true);
    }

    /**
     * Kept for Symfony 6.4 / 7.x, where the interface still requires it.
     * Deprecated since Symfony 7.3 and removed from the interface in 8.0.
     */
    #[\Deprecated('Credentials are cleared explicitly with setPlainPassword(null).')]
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

     public function isSuperAdmin(): bool
    {
        return $this->hasRole(static::ROLE_SUPER_ADMIN);
    }

    public function setSuperAdmin(bool $boolean): void
    {
        if (true === $boolean) {
            $this->addRole(static::ROLE_SUPER_ADMIN);
        } else {
            $this->removeRole(static::ROLE_SUPER_ADMIN);
        }
    }

    public function getUsernameCanonical(): ?string
    {
        return $this->usernameCanonical;
    }

    public function getEmailCanonical(): ?string
    {
        return $this->emailCanonical;
    }


    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function setUsernameCanonical(?string $usernameCanonical): void
    {
        $this->usernameCanonical = $usernameCanonical;
    }

    public function setEmailCanonical(?string $emailCanonical): void
    {
        $this->emailCanonical = $emailCanonical;
    }

     public function setLastLogin(?\DateTimeInterface $time = null): void
    {
        $this->lastLogin = $time;
    }

    public function setConfirmationToken(?string $confirmationToken): void
    {
        $this->confirmationToken = $confirmationToken;
    }

    public function setPasswordRequestedAt(?\DateTimeInterface $date = null): void
    {
        $this->passwordRequestedAt = $date;
    }

    public function getPasswordRequestedAt(): ?\DateTimeInterface
    {
        return $this->passwordRequestedAt;
    }

     /**
     * Get the value of tokenRequestedAt
     */
    public function getTokenRequestedAt(): ?\DateTimeInterface
    {
        return $this->tokenRequestedAt;
    }

    /**
     * Set the value of tokenRequestedAt
     */
    public function setTokenRequestedAt(?\DateTimeInterface  $tokenRequestedAt): void
    {
        $this->tokenRequestedAt = $tokenRequestedAt;
    }

    public function hasPassword(): bool
    {
        // Utilise une vérification stricte pour s'assurer que le mot de passe n'est pas NULL et n'est pas vide.
        return $this->password !== null && $this->password !== '';
    }

    public function __toString(): string
    {
        return $this->getUserIdentifier();
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getStatus():bool{
        
        return $this->isEnabled();
    }

     /**
     * Get the value of slug
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }
}