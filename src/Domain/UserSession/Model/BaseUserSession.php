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

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Domain\UserSession\Enum\UserSessionRevokedReason;

/**
 * Classe de base pour les sessions utilisateur.
 *
 * Porte toute la logique métier partagée entre les implémentations concrètes.
 * L'entité Doctrine UserSession étend cette classe et y ajoute uniquement
 * les attributs de mapping ORM.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
abstract class BaseUserSession implements UserSessionInterface, \Stringable
{
    protected int|string|null $id = null;

    /** Identifiant unique de l'utilisateur (email, username, UUID…) */
    protected string|int|null $userIdentifier = null;

    /** ID de session Symfony (PHPSESSID) */
    protected ?string $sessionId = null;

    protected ?string $ipAddress = null;

    protected ?string $userAgent = null;

    /** Empreinte appareil : hash de User-Agent + Accept-Language */
    protected ?string $deviceFingerprint = null;

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $lastActivityAt = null;

    /**
     * true  → session courante et valide
     * false → révoquée (connexion sur un autre appareil ou expirée manuellement)
     */
    protected bool $active = true;

     /**
     * Raison de la révocation — null tant que la session est active.
     * Permet à SessionControlListener d'afficher le bon message / la bonne
     * redirection lorsqu'il retrouve cette session révoquée.
     */
    protected ?UserSessionRevokedReason $revokedReason = null;
    
    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getUserIdentifier(): ?string
    {
        return (string) $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getDeviceFingerprint(): ?string
    {
        return $this->deviceFingerprint;
    }

    public function setDeviceFingerprint(?string $deviceFingerprint): void
    {
        $this->deviceFingerprint = $deviceFingerprint;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getLastActivityAt(): ?\DateTimeInterface
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(\DateTimeInterface $lastActivity): void
    {
        $this->lastActivityAt = $lastActivity;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Vérifie si la session est expirée selon un seuil d'inactivité.
     *
     * La session est considérée expirée si lastActivityAt est antérieure à
     * (maintenant - $hoursOfInactivity heures).
     */
    public function isExpired(int $hoursOfInactivity = 2): bool
    {
        if (null === $this->lastActivityAt) {
            return true;
        }

        $threshold = new \DateTimeImmutable(
            \sprintf('-%d hours', $hoursOfInactivity),
            new \DateTimeZone('UTC'),
        );

        return $this->lastActivityAt < $threshold;
    }
    
    /**
     * Vérifie si cette session appartient à un utilisateur donné.
     */
    public function belongsToUser(BaseUserInterface $user): bool
    {
        return (string) $this->userIdentifier === $user->getUserIdentifier();
    }

    /**
     * Met à jour la date de dernière activité à maintenant (UTC).
     * Implémentée dans la classe concrète pour utiliser le bon type DateTime.
     */
    abstract public function updateActivity(): void;

    /**
     * Alias de updateActivity().
     */
    public function touch(): void
    {
        $this->updateActivity();
    }

    public function __toString(): string
    {
        return (string) $this->userIdentifier;
    }


    /**
     * Révoque la session → empêche toute future validation.
     *
     * @param UserSessionRevokedReason $reason Raison de la révocation, tracée
     *                                         pour permettre l'affichage du bon
     *                                         message lors de la prochaine requête
     *                                         de ce navigateur.
     */
    public function revoke(UserSessionRevokedReason $reason): void
    {
        $this->active = false;
        $this->revokedReason = $reason;
    }

    public function getRevokedReason(): ?UserSessionRevokedReason
    {
        return $this->revokedReason;
    }

    public function setRevokedReason(?UserSessionRevokedReason $revokedReason): void
    {
        $this->revokedReason = $revokedReason;
    }
}
