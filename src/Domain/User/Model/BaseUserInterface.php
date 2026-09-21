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

/**
 * Contract of a user managed by the bundle.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface BaseUserInterface extends  \Stringable
{
    public function getId(): int|string|null;

    public function getEmail(): ?string;

    public function setEmail(string $email): static;

    public function getEmailCanonical(): ?string;

    public function setEmailCanonical(?string $emailCanonical): void;


    
    public function setUsername(string $username): void;

    public function getUsername(): ?string;
    
    public function getUsernameCanonical(): ?string;

    public function setUsernameCanonical(?string $usernameCanonical): void;


    public function setPassword(string $password): static;

     /**
     * Checks if the user has a hashed password defined in the database.
     * This is crucial for determining local login capability vs. external authentication.
     *
     * @return bool Returns true if the 'password' property is not null and not an empty string.
     */
    public function hasPassword():bool ;

    public function getPlainPassword(): ?string;

    public function setPlainPassword(?string $plainPassword): static;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): static;

    public function isEmailVerified(): bool;

    public function setEmailVerified(bool $emailVerified): static;

    public function getEmailVerifiedAt(): ?\DateTimeImmutable ;

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt):void ;


    public function isSuperAdmin(): bool ;

    public function setSuperAdmin(bool $boolean): void ;


    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static;

    public function addRole(string $role): static;

    public function removeRole(string $role): static;

    public function hasRole(string $role): bool;


    public function getConfirmationToken(): ?string;

    public function setConfirmationToken(?string $confirmationToken): void;

    public function getTokenRequestedAt(): ?\DateTimeInterface;

    public function setTokenRequestedAt(?\DateTimeInterface $tokenRequestedAt): void ;


    public function getPasswordRequestedAt(): ?\DateTimeInterface;

    public function setPasswordRequestedAt(?\DateTimeInterface $date = null): void;

    public function isPasswordRequestNonExpired(int $ttl): bool;


    public function setLastLogin(?\DateTimeInterface $time = null): void;

    public function getLastLogin(): ?\DateTimeInterface;

    public function setCreatedAt(?\DateTimeInterface $createdAt = null): void;

    public function getCreatedAt(): ?\DateTimeInterface;

    public function setUpdatedAt(?\DateTimeInterface $updatedAt = null): void;

    public function getUpdatedAt(): ?\DateTimeInterface;
}