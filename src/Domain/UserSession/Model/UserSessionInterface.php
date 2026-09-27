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

namespace Wlindabla\UserBundle\Domain\UserSession\Model;

use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;

interface UserSessionInterface
{
    public function getId(): int|string|null;

    public function getUserIdentifier(): ?string;

    public function setUserIdentifier(string $userIdentifier): void;

    public function getIpAddress(): ?string;

    public function setIpAddress(?string $ipAddress): void;

    public function getUserAgent(): ?string;

    public function setUserAgent(?string $userAgent): void;

    public function getCreatedAt(): ?\DateTimeInterface;

    public function setCreatedAt(\DateTimeInterface $createdAt): void;

    public function getLastActivityAt(): ?\DateTimeInterface;

    public function setLastActivityAt(\DateTimeInterface $lastActivity): void;

    public function getSessionId(): ?string;

    public function setSessionId(string $sessionId): void;

    /**
     * Indique si la session est expirée selon un seuil d'inactivité.
     */
    public function isExpired(int $hoursOfInactivity = 2): bool;

    /**
     * Met à jour la date de dernière activité à maintenant.
     */
    public function updateActivity(): void;

    /**
     * Alias de updateActivity() — même sémantique que touch() en Symfony.
     */
    public function touch(): void;

    /**
     * Révoque la session (is_active → false).
     */
    public function revoke(UserSessionRevokedReason $reason): void;

    public function isActive(): bool;

    public function getDeviceFingerprint(): ?string;

    public function setDeviceFingerprint(?string $deviceFingerprint): void;

    public function getRevokedReason(): ?UserSessionRevokedReason ;
}