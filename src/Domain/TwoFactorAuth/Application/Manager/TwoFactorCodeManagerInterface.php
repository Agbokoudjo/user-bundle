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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager;

use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\InvalidTwoFactorCodeException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorCodeLockedException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception\TwoFactorSendThrottledException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;

/**
 * Contract of the email MFA orchestration service.
 *
 * generateAndSend() and verify() are synchronous: the controller needs
 * the result immediately. Only the email itself is sent asynchronously,
 * by the mailer.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
interface TwoFactorCodeManagerInterface
{
    /**
     * Generates a new code, invalidates the user's previous PENDING
     * codes, persists the hash, and triggers the email.
     *
     * Synchronous entry point: used by controllers and any caller that
     * needs to react to the throttle itself (e.g. return a 429).
     *
     * @throws TwoFactorSendThrottledException if the resend window
     *                                          (3 sends / 15 min) is exceeded
     */
    public function generateAndSend(string $userIdentifier, ?string $ipAddress, string $context = 'login'): ?TwoFactorCodeInterface;

    /**
     * Same as generateAndSend(), for use from an asynchronous context
     * (dispatched via Messenger) where nothing can act on a thrown
     * TwoFactorSendThrottledException — retrying it would just reproduce
     * the same, non-transient, rate-limit state. Failures, including the
     * throttle, are logged here and never thrown.
     */
    public function generateAndSendAsync(string $userIdentifier, ?string $ipAddress, string $context = 'login'): void;

    /**
     * Verifies the submitted code for the given user.
     *
     * @throws InvalidTwoFactorCodeException
     * @throws TwoFactorCodeLockedException
     */
    public function verify(string $userIdentifier, string $submittedCode): void;

    /**
     * Returns the user's active PENDING code, or null.
     */
    public function findActivePending(string $userIdentifier): ?TwoFactorCodeInterface;

    /**
     * Invalidates (EXPIRED) all of a user's PENDING codes. Used at logout
     * and when a new code is requested.
     */
    public function invalidatePending(string $userIdentifier): void;
}