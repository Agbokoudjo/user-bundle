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

use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Orchestrates the double-confirmation (and, where required, double
 * human validation) email change flow end to end.
 *
 * Who may request a change for someone else, which accounts are exempt
 * from double validation on a self-change, and the entire eligibility/
 * quorum logic are NOT decided here — see
 * EmailChangeValidatorPolicyInterface (quorum) and this bundle's
 * wlindabla_user.email_change config (exemptions, token lifetime,
 * cooldown).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface EmailChangeRequestServiceInterface
{
    /** Default token/request lifetime in seconds (1 hour). */
    public const int TOKEN_LIFETIME = 3600;

    /** Default minimum delay between two requests for the same account (in seconds). */
    public const int RETRY_COOLDOWN = 86400;

    /**
     * Starts a normal double-confirmation request.
     *
     * @throws \DomainException if an active request already exists for
     *                           $targetUser, if the retry cooldown hasn't
     *                           elapsed, or if $targetUser has no current
     *                           email to snapshot
     */
    public function requestChange(BaseUserInterface $requestedBy, BaseUserInterface $targetUser, string $newEmail): void;

    /**
     * Starts a RECOVERY request — $targetUser has lost access to their old
     * address, so no old-email confirmation is possible; double
     * validation is always required, with no exemption. Which accounts
     * may call this at all is left to the consuming application (a
     * typical choice is to restrict it to administrators, enforced
     * before this method is even called, or inside a concrete
     * implementation's own override).
     *
     * @throws \DomainException under the same conditions as requestChange()
     */
    public function requestRecovery(BaseUserInterface $requestedBy, BaseUserInterface $targetUser, string $newEmail): void;

    /**
     * @throws InvalidTokenException
     */
    public function confirmOldEmail(string $rawToken, int $requestId): void;

    /**
     * @throws InvalidTokenException
     */
    public function confirmNewEmail(string $rawToken, int $requestId): void;

    /**
     * Cancels the request via the "this wasn't me" link sent to the old
     * address — a security signal, not a routine cancellation.
     *
     * @throws InvalidTokenException
     */
    public function cancelByNotMe(string $rawToken, int $requestId): void;

    /**
     * Records a validator's decision during double validation, then
     * re-evaluates the quorum (via EmailChangeValidatorPolicyInterface)
     * and finalizes or closes the request accordingly.
     *
     * @throws \DomainException if the request isn't awaiting validation,
     *                           if $validator is not eligible, if they
     *                           already voted, or if a rejection is
     *                           missing its required comment
     */
    public function submitValidation(
        EmailChangeRequestInterface $request,
        BaseUserInterface $validator,
        bool $approved,
        ?string $comment = null,
    ): void;
}