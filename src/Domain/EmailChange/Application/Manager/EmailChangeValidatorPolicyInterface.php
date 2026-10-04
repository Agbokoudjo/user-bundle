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

namespace Wlindabla\UserBundle\Domain\EmailChange\Application\Manager;

use Wlindabla\UserBundle\Domain\EmailChange\Enum\EmailChangeValidationOutcome;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Entirely owned by the consuming application — this bundle ships no
 * implementation, only this contract. It is the single place where "who
 * can validate this request, under what title, and how many such
 * approvals are enough" is decided — whatever that means for a given
 * project (a committee structure, a flat permission, a fixed list of
 * administrators, anything).
 *
 * EmailChangeRequestServiceInterface calls this interface and this
 * interface alone to make every eligibility/quorum decision — it never
 * hardcodes any such rule itself.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface EmailChangeValidatorPolicyInterface
{
    /**
     * Under which title, if any, is $candidate's vote admissible on
     * $request? Returns null if $candidate is not eligible at all — this
     * MUST include the case where $candidate is the request's own target
     * user: self-validation is never allowed, and enforcing that is this
     * method's responsibility, not the bundle's.
     *
     * The returned string becomes
     * EmailChangeValidationInterface::getValidatorRole() when the vote is
     * recorded.
     */
    public function resolveEligibility(EmailChangeRequestInterface $request, BaseUserInterface $candidate): ?string;

    /**
     * Computes the current quorum verdict from the decisions already
     * recorded on $request — always derived fresh from $decisions, never
     * cached by the implementation (EmailChangeRequestServiceInterface
     * calls this right after every new vote).
     *
     * @param iterable<\Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface> $decisions
     */
    public function evaluate(EmailChangeRequestInterface $request, iterable $decisions): EmailChangeValidationOutcome;

    /**
     * Email addresses of everyone currently eligible to cast a vote on
     * $request — used to notify potential validators once the request
     * enters AwaitingValidation. The bundle has no way to know who those
     * people are; this is the only place that can answer.
     *
     * @return list<string>
     */
    public function listEligibleValidatorEmails(EmailChangeRequestInterface $request): array;
}