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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Model;

use Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum\TwoFactorCodeStatus;

/**
* Business contract for an MFA code (13 digits + 7 letters, sent via email). 
*
* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
*/
interface TwoFactorCodeInterface
{
    public const int DEFAULT_MAX_ATTEMPTS = 5;
    public const int DEFAULT_TTL_MINUTES = 10;
    public const int DEFAULT_MAX_SEND_PER_WINDOW = 3;
    public const int DEFAULT_SEND_WINDOW_MINUTES = 15;

    public function getId(): int|string|null;

    public function getUserIdentifier(): ?string;

    public function setUserIdentifier(string $userIdentifier): void;

   /** Hash (hash_hmac sha256) of the code — never the code in plain text. */
    public function getCodeHash(): ?string;

    public function setCodeHash(string $codeHash): void;

    public function getStatus(): TwoFactorCodeStatus;

    public function setStatus(TwoFactorCodeStatus $status): void;

    public function getExpiresAt(): ?\DateTimeInterface;

    public function setExpiresAt(\DateTimeInterface $expiresAt): void;

    public function getAttempts(): int;

    public function incrementAttempts(): void;

    public function getMaxAttempts(): int;

    public function getLastSentAt(): ?\DateTimeInterface;

    public function setLastSentAt(\DateTimeInterface $lastSentAt): void;

    public function getSendCount(): int;

    public function incrementSendCount(): void;

    public function getIpAddress(): ?string;

    public function setIpAddress(?string $ipAddress): void;

    public function getCreatedAt(): ?\DateTimeInterface;

    public function setCreatedAt(\DateTimeInterface $createdAt): void;

    public function isExpired(): bool;

    public function isLocked(): bool;

    public function isPending(): bool;
}
