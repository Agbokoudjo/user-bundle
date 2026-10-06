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

namespace Wlindabla\UserBundle\Domain\User\Service;

use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;

/**
 * Interface contract for the "forgot password" workflow.
 *
 * Workflow overview:
 * 1. User submits their email or username -> `requestReset()` generates a secure token,
 *    hashes it, stores it, and triggers an asynchronous confirmation email dispatch.
 * 2. User clicks the received link -> `confirmReset()` validates the token and,
 *    for enhanced security (e.g., healthcare sector compliance), generates a temporary
 *    password dispatched via email (preventing direct, unauthenticated plain-text password
 *    input from email links).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface PasswordResetInterface
{
    /**
     * Minimum cooldown period between two consecutive reset requests (in seconds).
     * Prevents email spamming and mitigates account enumeration attacks.
     */
    public const int RETRY_COOLDOWN = 60; // 1 minute

    /**
     * Lifetime duration of the reset confirmation token in seconds (1 hour).
     */
    public const int TOKEN_LIFETIME = 3600;

    /**
     * Processes a password reset request.
     *
     * To prevent user enumeration vectors, this method MUST NOT throw exceptions
     * or leak information if a user account is not found. The observable response
     * on the client side must remain identical regardless of user existence.
     *
     * @param string $usernameOrEmail User identifier or email submitted via the form.
     */
    public function requestReset(string $usernameOrEmail): void;

    /**
     * Confirms the token received via email and triggers temporary password generation,
     * which is then securely sent to the user's registered email address.
     *
     * @param string     $rawToken Plain-text token provided in the URL query parameters.
     * @param string|int $id       Decrypted internal identifier of the target user.
     *
     * @throws InvalidTokenException If the token is invalid, expired, already consumed,
     *                                or if no associated user is found.
     */
    public function confirmReset(string $rawToken, string|int $id): void;
}