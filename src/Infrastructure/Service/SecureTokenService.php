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

namespace Wlindabla\UserBundle\Infrastructure\Service;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Generator\TokenGeneratorInterface;
use Wlindabla\ShareServiceBundle\Security\Hash\TokenHasherInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Event\UserAccountEmailVerificationEvent;
use Wlindabla\UserBundle\Domain\User\Exception\EmailAlreadyVerifiedException;
use Wlindabla\UserBundle\Domain\User\Exception\TokenGenerationCooldownException;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Service responsible for generating and dispatching secure tokens for email verification.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package Wlindabla\UserBundle\Infrastructure\Service
 */
final class SecureTokenService
{
    /**
     * Minimum delay between two token generations (in seconds).
     * Protects against abuse and spam requests.
     */
    private const TOKEN_GENERATION_COOLDOWN = 60;
    
    public function __construct(
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly EventDispatcherInterface $eventBusDispatcher,
        private readonly UserManagerInterface $userManager,
    ) {}

    /**
     * Generates and stores a new email confirmation token.
     * 
     * This method:
     * 1. Validates the user state and data
     * 2. Checks rate limiting/cooldown enforcement
     * 3. Generates a cryptographically secure token
     * 4. Hashes the token for secure storage
     * 5. Persists the user changes to the database
     * 6. Dispatches an event to trigger confirmation email dispatch
     * 
     * The plain-text token is passed via the dispatched event for email inclusion.
     * Only the hashed token is stored in the database.
     *
     * @param BaseUserInterface $user The user instance requiring token generation
     * @param int $length The desired length of the token (defaults to 32 characters)
     * 
     * @return void
     * 
     * @throws InvalidArgumentException If user or length inputs are invalid
     * @throws EmailAlreadyVerifiedException If the user's email address is already verified
     * @throws TokenGenerationCooldownException If requested before the cooldown period expires
     * @throws RuntimeException If token generation or persistence fails
     * 
     * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
     */
    public function generateEmailConfirmationToken(
        BaseUserInterface $user,
        int $length = TokenGeneratorInterface::DEFAULT_EMAIL_TOKEN_LENGTH
    ): void {
        // 1. User data validation
        $this->validateUser($user);
       
        // 2. Enforce rate limiting cooldown
        $this->enforceTokenGenerationCooldown($user);

        // 3. Generate plain-text token (cryptographically secure)
        $plainToken = $this->tokenGenerator->generate($length);

        // 4. Hash token for secure database storage
        $hashedToken = $this->tokenHasher->hash($plainToken);

        // 5. Update user entity timestamps and token
        $user->setConfirmationToken($hashedToken);
        $user->setTokenRequestedAt(new \DateTime('now', new \DateTimeZone('UTC'))); // Field type in DB is datetime

        // 6. Persist changes to database
        $this->userManager->save($user);
        
        // 7. Dispatch event for email handling
        $event = new UserAccountEmailVerificationEvent(
            $user->getUsername(),
            $user->getEmail(),
            $user->getId(),
            $plainToken
        );
        $this->eventBusDispatcher->dispatch($event);
    }

    /**
     * Validates that the user is eligible to receive a confirmation token.
     * Although user validation is performed in the presentation/request layer,
     * defense-in-depth is enforced here.
     * 
     * @param BaseUserInterface $user The user to validate
     * 
     * @throws InvalidArgumentException If the user is missing a valid email address
     * @throws EmailAlreadyVerifiedException If the email is already verified
     */
    private function validateUser(BaseUserInterface $user): void
    {
        if (empty($user->getEmail())) {
            throw new InvalidArgumentException(
                'The user must have a valid email address to receive a confirmation token.'
            );
        }

        if ($user->isEmailVerified()) {
            throw EmailAlreadyVerifiedException::forEmail($user->getEmail());
        }
    }

    /**
     * Enforces rate limiting on token generation.
     * 
     * Prevents system abuse by throttling token generation requests per user.
     *
     * @param BaseUserInterface $user The user requesting a token
     * 
     * @throws TokenGenerationCooldownException If minimum delay between requests is violated
     */
    private function enforceTokenGenerationCooldown(BaseUserInterface $user): void
    {
        $lastRequestedAt = $user->getTokenRequestedAt();

        if ($lastRequestedAt === null) {
            // First request, proceed
            return;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timeSinceLastRequest = $now->getTimestamp() - $lastRequestedAt->getTimestamp();

        if ($timeSinceLastRequest < self::TOKEN_GENERATION_COOLDOWN) {
            $remainingTime = self::TOKEN_GENERATION_COOLDOWN - $timeSinceLastRequest;

            throw new TokenGenerationCooldownException($remainingTime);
        }
    }
}