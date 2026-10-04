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

namespace Wlindabla\UserBundle\Domain\EmailChange\Model;

use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeCancelReason;
use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeRequestStatus;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * A double-confirmation email change request — old and new addresses are
 * both snapshotted at creation time (never re-read from the live User
 * entity mid-flow, so the audit trail stays accurate even if the email
 * changed again through another path).
 *
 * The actual change to the User entity only happens once:
 *   1. both confirmations are recorded (oldEmailConfirmedAt and
 *      newEmailConfirmedAt — or only the latter when isRecovery), AND
 *   2. if requiresDoubleValidation is true, a second, independent human
 *      validation quorum has been satisfied (see
 *      EmailChangeValidatorPolicyInterface — this bundle only tracks that
 *      a validation is required and its outcome, the actual eligibility
 *      and quorum rules are entirely owned by the consuming application).
 *
 * isRecovery marks a request where the old address is, by definition,
 * unreachable (lost access) — no old-email confirmation is possible, so
 * oldEmailTokenHash stays null and isFullyConfirmed() only requires the
 * new address. What counts as sufficient human validation for a recovery
 * is, again, entirely up to EmailChangeValidatorPolicyInterface.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface EmailChangeRequestInterface
{
    public function getId(): ?int;

    public function getRequestedBy(): ?BaseUserInterface;

    public function getTargetUser(): ?BaseUserInterface;

    public function getOldEmail(): ?string;

    public function getNewEmail(): ?string;

    public function getOldEmailTokenHash(): ?string;

    public function getNewEmailTokenHash(): ?string;

    public function getOldEmailConfirmedAt(): ?\DateTimeImmutable;

    public function getNewEmailConfirmedAt(): ?\DateTimeImmutable;

    public function confirmOldEmail(): void;

    public function confirmNewEmail(): void;

    public function isFullyConfirmed(): bool;

    public function isRecovery(): bool;

    public function requiresDoubleValidation(): bool;

    public function getStatus(): EmailChangeRequestStatus;

    public function markAwaitingValidation(): void;

    public function markConfirmed(): void;

    public function markCancelled(EmailChangeCancelReason $reason): void;

    public function markExpired(): void;

    public function getCancelledReason(): ?EmailChangeCancelReason;

    public function getRequestedAt(): \DateTimeImmutable;

    public function getExpiresAt(): \DateTimeImmutable;

    public function isExpired(): bool;

    public function getAppliedAt(): ?\DateTimeImmutable;

    /**
     * @return iterable<EmailChangeValidationInterface>
     */
    public function getValidations(): iterable;
}