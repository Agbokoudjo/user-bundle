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
 * Interface for user email verification.
 * 
 * Defines the contract to validate email confirmation tokens
 * and activate user accounts upon successful verification.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package Wlindabla\UserBundle\Domain\User\Service
 */
interface EmailVerificationInterface
{
    /**
     * Minimum delay between two verification requests (in seconds).
     */
    public const RESEND_COOLDOWN = 60; // 1 minute

    /**
     * Lifetime of a verification token in seconds (1 hour).
     */
    public const TOKEN_LIFETIME = 3600;

    /**
     * Validates the confirmation token and activates the user account.
     * 
     * This method performs the following checks:
     * - User existence matching the provided identifier
     * - Token validity (comparison with the stored hash)
     * - Token expiration status (based on TOKEN_LIFETIME)
     * - Token single-use check (ensures token has not been used yet)
     * 
     * If all checks pass:
     * - Marks the email as verified
     * - Removes the confirmation token
     * - Automatically activates the account for standard users (Client, Simple)
     * - Admin/Member accounts require manual activation by an administrator
     *
     * @param string $rawToken The plain text token received via the verification URL
     * @param string|int $id The unique identifier of the user
     * 
     * @return void
     * 
     * @throws InvalidTokenException If the token is invalid, expired, already used, or if the user is not found
     *                               - Code CODE_INVALID (1001): Token does not match the stored hash
     *                               - Code CODE_EXPIRED (1002): Token has exceeded its lifetime
     *                               - Code CODE_ALREADY_USED (1003): Token has already been consumed
     *                               - Code CODE_USER_NOT_FOUND (1004): User could not be found
     */
    public function verifyEmail(string $rawToken, string|int $id): void;

    /**
     * Generates and sends a new email verification token.
     * Token generation and rate limiting are delegated to SecureTokenService.
     * 
     * This method:
     * 1. Checks user existence
     * 2. Verifies whether the email is not already verified
     * 3. Applies rate limiting (minimum cooldown between requests)
     * 4. Generates a new cryptographically secure token
     * 5. Hashes and stores the token
     * 6. Dispatches the email notification event
     *
     * @param string|int $id The unique identifier of the user
     * 
     * @return void
     * 
     * @throws InvalidTokenException If the user does not exist, the email is already verified,
     *                               or if the cooldown period between requests has not elapsed
     * @throws \RuntimeException If token generation or hashing fails
     */
    public function resendVerificationEmail(string|int $id): void;
}