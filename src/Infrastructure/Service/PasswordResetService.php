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

use Psr\Log\LoggerInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Generator\TokenGeneratorInterface;
use Wlindabla\ShareServiceBundle\Security\Hash\TokenHasherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\GenerateTemporaryPasswordHandler;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\PasswordResetInterface;
use Wlindabla\UserBundle\Infrastructure\Service\PasswordResetMailerService;

/**
* Manages the "forgotten password" flow from end to end. 
*
* For security reasons (given that end-users are in the healthcare sector),
* entering a new password directly via the email link is not permitted; 
* confirmation merely triggers the generation of a strong temporary password,
* sent by email, which the user can change once logged in.
*
* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
* @package App\Infrastructure\Service
*/
final readonly class PasswordResetService implements PasswordResetInterface
{
    public function __construct(
        private UserManagerInterface $userManager,
        private TokenGeneratorInterface $tokenGenerator,
        private TokenHasherInterface $tokenHasher,
        private AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private PasswordResetMailerService $passwordResetMailerService,
        private LoggerInterface $logger,
    ) {}

    public function requestReset(string $usernameOrEmail): void
    {
        $user = $this->userManager->findUserByUsernameOrEmail($usernameOrEmail);

        if (null === $user) {
           // Never reveal that no account matches (anti-enumeration).
            $this->logger->info('Password reset request for an unknown username.', [
                'identifier' => $usernameOrEmail,
            ]);

            return;
        }

        if (!$user->isEnabled() || !$user->isAccountNonLocked()) {
            $this->logger->info('Password reset request ignored: account deactivated or locked.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        // Anti-spam : on ne régénère pas de token tant que le précédent est encore valide.
        if ($user->isPasswordRequestNonExpired(self::RETRY_COOLDOWN)) {
            $this->logger->notice('Password reset request ignored: cooldown active.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $plainToken = $this->tokenGenerator->generate(TokenGeneratorInterface::DEFAULT_PASSWORD_RESET_TOKEN_LENGTH);
        $hashedToken = $this->tokenHasher->hash($plainToken);

        $user->setConfirmationToken($hashedToken);
        $user->setPasswordRequestedAt(new \DateTime('now', new \DateTimeZone('UTC')));

        $this->userManager->save($user);

        $this->passwordResetMailerService->sendResetRequestEmail($user->getId(), $plainToken);


        $this->logger->info('Password reset request accepted; email is being sent.', [
            'user_id' => $user->getId(),
        ]);
    }

    public function confirmReset(string $rawToken, string|int $id): void
    {
        $user = $this->userManager->find($id);

        if (null === $user) {
            throw InvalidTokenException::userNotFound();
        }

        $hashedToken = $user->getConfirmationToken();
        $requestedAt = $user->getPasswordRequestedAt();

        if (null === $hashedToken || null === $requestedAt) {
            throw InvalidTokenException::alreadyUsedToken();
        }

        if (!$user->isPasswordRequestNonExpired(self::TOKEN_LIFETIME)) {
            throw InvalidTokenException::expiredToken(
                (clone $requestedAt)->modify(\sprintf('+%d seconds', self::TOKEN_LIFETIME))
            );
        }

        if (!$this->tokenHasher->verify($rawToken, $hashedToken)) {
            $this->logger->error('Password reset attempt with an invalid token (hash mismatch).', [
                'user_id' => $user->getId(),
            ]);

            throw InvalidTokenException::invalidToken();
        }

        // The token has served its purpose: it is consumed immediately to prevent
        // any reuse, even before the temporary password is generated.
        $user->setConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $this->userManager->save($user);

        $this->asyncMethodDispatcher->dispatch(
            GenerateTemporaryPasswordHandler::class,
            'process',
            [$user->getId()]
        );

        $this->logger->info('Password reset link confirmed; temporary password generation triggered.', [
            'user_id' => $user->getId(),
        ]);
    }
}
