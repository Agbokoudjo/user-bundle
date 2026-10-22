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
* Domain model contract representing a user login record. 
*
* A login attempt (whether successful or failed) is an immutable historical fact;
* therefore, this contract exposes only the accessors necessary for creating and
* reading the log—never for modification after persistence. 
*
* Note: `createdAt` and `updatedAt` are not part of this contract; they are managed
* by `TimestampableEntity` and `DoctrineTimestampableListener` within the Infrastructure layer. 
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