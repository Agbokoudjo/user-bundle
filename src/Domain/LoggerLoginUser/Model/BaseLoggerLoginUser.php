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

namespace Wlindabla\UserBundle\Domain\LoggerLoginUser\Model;

use Wlindabla\UserBundle\Domain\LoggerLoginUser\Model\LoggerLoginUserInterface;

/**
 * Modèle de domaine de base représentant un enregistrement de connexion utilisateur.
 *
 * Ce modèle est immuable par conception : une tentative de connexion (réussie ou échouée)
 * est un fait historique qui ne doit jamais être modifié après création.
 *
 * createdAt/updatedAt sont volontairement absents : ils sont portés par
 * TimestampableEntity côté entité Doctrine (Infrastructure).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class BaseLoggerLoginUser implements LoggerLoginUserInterface
{
    protected string $username;

    protected string $email;

    protected ?string $lastLoginIp = null;

    protected ?string $userAgent = null;

    protected ?string $userAgentLabel = null;

    public function getUserAgentLabel(): ?string
    {
        return $this->userAgentLabel;
    }

    public function setUserAgentLabel(?string $userAgentLabel): static
    {
        $this->userAgentLabel = $userAgentLabel;

        return $this;
    }
    
    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(?string $lastLoginIp): static
    {
        $this->lastLoginIp = $lastLoginIp;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    
}