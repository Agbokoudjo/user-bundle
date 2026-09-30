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

/**
 * Contrat du modèle de domaine représentant un enregistrement de connexion utilisateur.
 *
 * Une tentative de connexion (réussie ou échouée) est un fait historique immuable :
 * ce contrat n'expose donc que les accesseurs nécessaires à la création du log
 * et à sa lecture, jamais de mutation post-persistance.
 *
 * NB : createdAt/updatedAt ne font pas partie de ce contrat — ils sont gérés
 * par TimestampableEntity + DoctrineTimestampableListener côté Infrastructure.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface LoggerLoginUserInterface
{
    public function getUsername(): string;

    public function setUsername(string $username): static;

    public function getEmail(): string;

    public function setEmail(string $email): static;

    public function getLastLoginIp(): ?string;

    public function setLastLoginIp(?string $lastLoginIp): static;

    public function getUserAgent(): ?string;

    public function setUserAgent(?string $userAgent): static;

    public function getUserAgentLabel(): ?string;

    public function setUserAgentLabel(?string $userAgentLabel): static;

    public function getCreatedAt(): ?\DateTimeInterface ;

    public function setCreatedAt(?\DateTimeInterface $createdAt=null): static ;
}