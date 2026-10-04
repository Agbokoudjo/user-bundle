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

use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * A single decision (approval or rejection) rendered by a validator on an
 * EmailChangeRequestInterface awaiting double validation.
 *
 * One row = one validator = one final decision. An existing decision is
 * never overwritten — a mistaken vote is closed by a new rejection row,
 * never edited after the fact (persistent audit requirement).
 *
 * validatorRole is a plain string, not a bundle-defined enum: the set of
 * titles under which someone may validate (committee president, a
 * catalog permission, an emergency witness, or anything else) is
 * entirely defined by the consuming application's own
 * EmailChangeValidatorPolicyInterface implementation — this bundle has no
 * opinion on what those titles are.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface EmailChangeValidationInterface
{
    public function getId(): ?int;

    public function getEmailChangeRequest(): ?EmailChangeRequestInterface;

    public function getValidator(): ?BaseUserInterface;

    public function getValidatorRole(): string;

    public function isApproved(): bool;

    public function getComment(): ?string;

    public function getDecidedAt(): \DateTimeImmutable;
}