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
 * Framework-agnostic base implementation of a business permission.
 * Doctrine mapping is added by the Infrastructure layer, not here — see
 * Infrastructure\Doctrine\PermissionRole.
 *
 * Carries audit/lifecycle fields (createdBy, currentManager, deletedAt,
 * deletedBy, deletedReason) directly as plain properties, the same
 * pattern used by every other domain model in this bundle — not via a
 * Doctrine trait, which belongs strictly to the Infrastructure layer.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
abstract class AbstractPermissionRole implements PermissionRoleInterface, ManagedCatalogInterface
{
    protected int|string|null $id = null;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $context = null;

    protected ?BaseUserInterface $createdBy = null;

    protected ?BaseUserInterface $currentManager = null;

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $updatedAt = null;

    protected ?\DateTimeInterface $deletedAt = null;

    protected ?BaseUserInterface $deletedBy = null;

    protected ?string $deletedReason = null;

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

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

    public function getCurrentManager(): ?BaseUserInterface
    {
        return $this->currentManager;
    }

    public function setCurrentManager(BaseUserInterface $currentManager): static
    {
        $this->currentManager = $currentManager;

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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getDeletedBy(): ?BaseUserInterface
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?BaseUserInterface $deletedBy): static
    {
        $this->deletedBy = $deletedBy;

        return $this;
    }

    public function getDeletedReason(): ?string
    {
        return $this->deletedReason;
    }

    public function setDeletedReason(?string $deletedReason): static
    {
        $this->deletedReason = $deletedReason;

        return $this;
    }

    /**
     * Revokes this permission — never a real deletion of the row, see
     * deletedAt/deletedBy/deletedReason above.
     */
    public function revoke(BaseUserInterface $revokedBy, ?string $reason = null): void
    {
        $this->deletedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->deletedBy = $revokedBy;
        $this->deletedReason = $reason;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}