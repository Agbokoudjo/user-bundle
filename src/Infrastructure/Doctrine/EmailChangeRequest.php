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

namespace Wlindabla\UserBundle\Infrastructure\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeCancelReason;
use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeRequestStatus;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;

/**
 * Doctrine mapping for a double-confirmation email change request. Extend
 * it in your application:
 *
 *   #[ORM\Entity]
 *   #[ORM\Table(name: 'email_change_requests')]
 *   class EmailChangeRequest extends \Wlindabla\UserBundle\Infrastructure\Doctrine\EmailChangeRequest {}
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[ORM\MappedSuperclass]
abstract class EmailChangeRequest implements EmailChangeRequestInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\UserInterface')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    protected ?BaseUserInterface $requestedBy = null;

    #[ORM\ManyToOne(targetEntity: 'Wlindabla\UserBundle\Infrastructure\Model\UserInterface')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?BaseUserInterface $targetUser = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected ?string $oldEmail = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected ?string $newEmail = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $oldEmailTokenHash = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected ?string $newEmailTokenHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $oldEmailConfirmedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $newEmailConfirmedAt = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $isRecovery = false;

    #[ORM\Column(type: 'boolean')]
    protected bool $requiresDoubleValidation = false;

    #[ORM\Column(type: 'string', length: 30, enumType: EmailChangeRequestStatus::class)]
    protected EmailChangeRequestStatus $status;

    #[ORM\Column(type: 'string', length: 30, nullable: true, enumType: EmailChangeCancelReason::class)]
    protected ?EmailChangeCancelReason $cancelledReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $appliedAt = null;

    /** @var Collection<int, EmailChangeValidationInterface> */
    #[ORM\OneToMany(mappedBy: 'emailChangeRequest', targetEntity: EmailChangeValidationInterface::class, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['decidedAt' => 'ASC'])]
    protected Collection $validations;

    public function __construct(
        BaseUserInterface $requestedBy,
        BaseUserInterface $targetUser,
        string $oldEmail,
        string $newEmail,
        ?string $oldEmailTokenHash,
        string $newEmailTokenHash,
        bool $requiresDoubleValidation,
        \DateTimeImmutable $expiresAt,
        bool $isRecovery = false,
    ) {
        $this->requestedBy = $requestedBy;
        $this->targetUser = $targetUser;
        $this->oldEmail = $oldEmail;
        $this->newEmail = $newEmail;
        $this->oldEmailTokenHash = $oldEmailTokenHash;
        $this->newEmailTokenHash = $newEmailTokenHash;
        $this->requiresDoubleValidation = $requiresDoubleValidation;
        $this->isRecovery = $isRecovery;
        $this->status = EmailChangeRequestStatus::Pending;
        $this->requestedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->expiresAt = $expiresAt;
        $this->validations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequestedBy(): ?BaseUserInterface
    {
        return $this->requestedBy;
    }

    public function getTargetUser(): ?BaseUserInterface
    {
        return $this->targetUser;
    }

    public function getOldEmail(): ?string
    {
        return $this->oldEmail;
    }

    public function getNewEmail(): ?string
    {
        return $this->newEmail;
    }

    public function getOldEmailTokenHash(): ?string
    {
        return $this->oldEmailTokenHash;
    }

    public function getNewEmailTokenHash(): ?string
    {
        return $this->newEmailTokenHash;
    }

    public function getOldEmailConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->oldEmailConfirmedAt;
    }

    public function getNewEmailConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->newEmailConfirmedAt;
    }

    public function confirmOldEmail(): void
    {
        $this->oldEmailConfirmedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function confirmNewEmail(): void
    {
        $this->newEmailConfirmedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function isFullyConfirmed(): bool
    {
        if ($this->isRecovery) {
            return null !== $this->newEmailConfirmedAt;
        }

        return null !== $this->oldEmailConfirmedAt && null !== $this->newEmailConfirmedAt;
    }

    public function isRecovery(): bool
    {
        return $this->isRecovery;
    }

    public function requiresDoubleValidation(): bool
    {
        return $this->requiresDoubleValidation;
    }

    public function getStatus(): EmailChangeRequestStatus
    {
        return $this->status;
    }

    public function markAwaitingValidation(): void
    {
        $this->status = EmailChangeRequestStatus::AwaitingValidation;
    }

    public function markConfirmed(): void
    {
        $this->status = EmailChangeRequestStatus::Confirmed;
        $this->appliedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function markCancelled(EmailChangeCancelReason $reason): void
    {
        $this->status = EmailChangeRequestStatus::Cancelled;
        $this->cancelledReason = $reason;
    }

    public function markExpired(): void
    {
        $this->status = EmailChangeRequestStatus::Expired;
        $this->cancelledReason = EmailChangeCancelReason::EXPIRED;
    }

    public function getCancelledReason(): ?EmailChangeCancelReason
    {
        return $this->cancelledReason;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return !$this->status->isFinal() && $this->expiresAt < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function getValidations(): iterable
    {
        return $this->validations;
    }
}