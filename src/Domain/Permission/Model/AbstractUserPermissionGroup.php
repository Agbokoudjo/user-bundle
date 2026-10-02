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

namespace Wlindabla\UserBundle\Domain\Permission\Model;

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Framework-agnostic base implementation of a group grant. Same family
 * and same conventions as AbstractUserPermissionRole — see its docblock
 * for the userId design trade-off.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractUserPermissionGroup implements UserPermissionGroupInterface
{
    protected int|string|null $id = null;

    protected int|string|null $userId = null;

    protected ?PermissionGroupInterface $permissionGroup = null;

    protected ?string $scope = null;

    protected ?BaseUserInterface $createdBy = null;

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $deletedAt = null;

    protected ?BaseUserInterface $deletedBy = null;

    protected ?string $deletedReason = null;

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function getUserId(): int|string|null
    {
        return $this->userId;
    }

    public function setUserId(int|string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getPermissionGroup(): ?PermissionGroupInterface
    {
        return $this->permissionGroup;
    }

    public function setPermissionGroup(PermissionGroupInterface $permissionGroup): static
    {
        $this->permissionGroup = $permissionGroup;

        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getCreatedBy(): ?BaseUserInterface
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?BaseUserInterface $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function revoke(BaseUserInterface $revokedBy, ?string $reason = null): void
    {
        $this->deletedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->deletedBy = $revokedBy;
        $this->deletedReason = $reason;
    }

    public function getDeletedBy(): ?BaseUserInterface
    {
        return $this->deletedBy;
    }

    public function getDeletedReason(): ?string
    {
        return $this->deletedReason;
    }

    public function __toString(): string
    {
        return \sprintf('%s → user #%s', $this->permissionGroup?->getLabel() ?? '?', (string) $this->userId);
    }
}