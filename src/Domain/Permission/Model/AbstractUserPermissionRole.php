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
 * Framework-agnostic base implementation of a single permission grant.
 *
 * userId is int|string, NOT a Doctrine relation to the User entity — a
 * deliberate trade-off: it fully decouples permission assignment from the
 * User entity's technical identity, at the cost of a real foreign-key
 * constraint and automatic UI integration (e.g. an admin dropdown picker)
 * for selecting the user, which the consuming application must then
 * provide itself.
 *
 * Two different "who" fields on this entity, not to be confused:
 * - userId: who RECEIVES the permission;
 * - createdBy (from ManagedCatalogInterface, set on the entity by the
 *   consuming application): who GRANTED it — traceability of the action
 *   itself, separate from its subject.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractUserPermissionRole implements UserPermissionRoleInterface
{
    protected ?int $id = null;

    protected int|string|null $userId = null;

    protected ?PermissionRoleInterface $permissionRole = null;

    protected ?string $scope = null;

    protected ?BaseUserInterface $createdBy = null;

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $deletedAt = null;

    protected ?BaseUserInterface $deletedBy = null;

    protected ?string $deletedReason = null;

    public function getId(): ?int
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

    public function getPermissionRole(): ?PermissionRoleInterface
    {
        return $this->permissionRole;
    }

    public function setPermissionRole(PermissionRoleInterface $permissionRole): static
    {
        $this->permissionRole = $permissionRole;

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

    /**
     * Revokes this grant. createdAt (inherited) already serves as the
     * grant date — no separate "assignedAt" field, that would be
     * redundant.
     */
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
        return \sprintf('%s → user #%s', $this->permissionRole?->getName() ?? '?', (string) $this->userId);
    }
}