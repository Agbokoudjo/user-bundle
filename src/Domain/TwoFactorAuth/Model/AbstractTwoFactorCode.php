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
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;

/**
 * Implémentation métier commune, indépendante de Doctrine.
 * L'entité d'infrastructure (App\Infrastructure\Doctrine\Entity\TwoFactorCode)
 * n'apporte que le mapping ORM.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
abstract class AbstractTwoFactorCode implements TwoFactorCodeInterface
{
    protected int|string|null $id = null;

    protected string $userIdentifier;

    protected string $codeHash;

    protected TwoFactorCodeStatus $status = TwoFactorCodeStatus::PENDING;

    protected \DateTimeInterface $expiresAt;

    protected int $attempts = 0;

    protected int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS;

    protected ?\DateTimeInterface $lastSentAt = null;

    protected int $sendCount = 1;

    protected ?string $ipAddress = null;

    protected \DateTimeInterface $createdAt;

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getCodeHash(): ?string
    {
        return $this->codeHash;
    }

    public function setCodeHash(string $codeHash): void
    {
        $this->codeHash = $codeHash;
    }

    public function getStatus(): TwoFactorCodeStatus
    {
        return $this->status;
    }

    public function setStatus(TwoFactorCodeStatus $status): void
    {
        $this->status = $status;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        ++$this->attempts;

        if ($this->attempts >= $this->maxAttempts) {
            $this->status = TwoFactorCodeStatus::LOCKED;
        }
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getLastSentAt(): ?\DateTimeInterface
    {
        return $this->lastSentAt;
    }

    public function setLastSentAt(\DateTimeInterface $lastSentAt): void
    {
        $this->lastSentAt = $lastSentAt;
    }

    public function getSendCount(): int
    {
        return $this->sendCount;
    }

    public function incrementSendCount(): void
    {
        ++$this->sendCount;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function isLocked(): bool
    {
        return $this->status === TwoFactorCodeStatus::LOCKED
            || $this->attempts >= $this->maxAttempts;
    }

    public function isPending(): bool
    {
        return $this->status === TwoFactorCodeStatus::PENDING && !$this->isExpired() && !$this->isLocked();
    }
}
