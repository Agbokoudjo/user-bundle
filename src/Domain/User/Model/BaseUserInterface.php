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

    public function setPassword(string $password): static;

    public function getPlainPassword(): ?string;

    public function setPlainPassword(?string $plainPassword): static;

    public function isEnabled(): bool;

    public function setEnabled(bool $enabled): static;

    public function isEmailVerified(): bool;

    public function setEmailVerified(bool $emailVerified): static;

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static;

    public function addRole(string $role): static;

    public function removeRole(string $role): static;

    public function hasRole(string $role): bool;
}