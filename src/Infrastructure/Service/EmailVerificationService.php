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
use Psr\Log\LoggerInterface;
use Wlindabla\ShareServiceBundle\Messenger\QueueHandler\AsyncMethodDispatcherInterface;
use Wlindabla\ShareServiceBundle\Security\Hash\TokenHasherInterface;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\ApplyEmailVerificationHandler;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Exception\EmailAlreadyVerifiedException;
use Wlindabla\UserBundle\Domain\User\Exception\InvalidTokenException;
use Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface;

/**
* User email verification service. 
*
* Handles the verification of email confirmation tokens sent to users
* during registration. Checks token expiration and validity before
* activating the account. 
*
* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
* @package App\Infrastructure\Service
*/
final class EmailVerificationService implements EmailVerificationInterface
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly SecureTokenService $secureTokenService,
        private readonly AsyncMethodDispatcherInterface $asyncMethodDispatcher,
        private readonly ApplyEmailVerificationHandler $applyEmailVerificationHandler,
    ) {}

    /**
     * {@inheritdoc} 
     * 
     * @throws InvalidTokenException Si le token est invalide, expiré ou déjà utilisé
     */
    public function verifyEmail(string $rawToken, string|int $id): void
    {
        $this->asyncMethodDispatcher->dispatch(
            LoggerInterface::class,
            'info',
            [
                'Email verification attempt',
                [
                    'id' => $id
                ]
            ]
        ) ;

        $user = $this->userManager->find($id);

        if ($user === null) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'warning',
                [
                    'User not found for email verification',
                    [
                        'id' => $id
                    ]
                ]
            );

            throw InvalidTokenException::userNotFound();
        }

        // 2. Récupération des données de token
        $hashedToken = $user->getConfirmationToken();
        $requestedAt = $user->getTokenRequestedAt();

        // 3. Vérification que le token existe
        if ($hashedToken === null || $requestedAt === null) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'info',
                ['Missing token or account already verified', [
                    'user_id' => $user->getId(),
                    'has_token' => $hashedToken !== null,
                    'has_requested_at' => $requestedAt !== null,
                ]],
            );

            if (null !== $user->getEmailVerifiedAt()) {
                throw InvalidTokenException::alreadyUsedToken($user->getEmailVerifiedAt());
            }

            throw InvalidTokenException::invalidToken('No verification was ever requested for this user');
        }

        //  Vérification de l'expiration du token
        if ($this->isTokenExpired($requestedAt)) {
            $expiresAt = $this->calculateExpirationDate($requestedAt);
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'warning',
                [
                    'Verification token expired',
                    [
                        'user_id' => $user->getId(),
                        'requested_at' => $requestedAt->format('Y-m-d H:i:s'),
                        'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                    ]
                ]
            );

            throw InvalidTokenException::expiredToken($expiresAt);
        }

        // Vérification de la validité du token (comparaison hash)
        if (!$this->tokenHasher->verify($rawToken, $hashedToken)) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'error',
                ['Invalid verification token (hash mismatch)', ['user_id' => $user->getId()]],
            );

            throw InvalidTokenException::invalidToken('Hash mismatch');
        }

        //  Vérifier si rehash nécessaire
        try {
            if ($this->tokenHasher->needsRehash($hashedToken)) {
                $this->asyncMethodDispatcher->dispatch(
                    LoggerInterface::class,
                    'info',
                    [
                        'Rehashing token avec paramètres plus récents'
                    ]
                );
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        }

        // 7. Applying changes (business logic) asynchronously
        // Synchronous application: closes the window between "token validated" and
        // "token invalidated"—a second visit to the same link during this
        // window (double-click, email client preview, network retry)
        // must no longer be able to re-validate a token that has already been consumed. 
        // Logging and the email-sending event, however, remain asynchronous:
        // that is where the async approach adds value (offloading the database
        // and SMTP under heavy load), rather than for the write operation
        // that determines whether the link remains valid.
        $this->applyEmailVerificationHandler->handle($user->getId());

        $this->asyncMethodDispatcher->dispatch(
            LoggerInterface::class,
            'info',
            [
                'Email successfully verified',
                [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
            ],
        );
        
    }
    
    /**
     * Vérifie si un token a expiré.
     *
     * @param \DateTimeInterface $requestedAt Date de création du token
     * 
     * @return bool True si expiré, false sinon
     */
    private function isTokenExpired(\DateTimeInterface $requestedAt): bool
    {
        $now = new \DateTimeImmutable();
        $expirationTimestamp = $requestedAt->getTimestamp() + self::TOKEN_LIFETIME;

        return $now->getTimestamp() > $expirationTimestamp;
    }

    /**
     * Calculates the expiration date of a token.
     *
     * @param \DateTimeInterface $requestedAt Date de création du token
     * 
     * @return \DateTimeImmutable Date d'expiration
     */
    private function calculateExpirationDate(\DateTimeInterface $requestedAt): \DateTimeImmutable
    {
        $expirationTimestamp = $requestedAt->getTimestamp() + self::TOKEN_LIFETIME;

        return (new \DateTimeImmutable())->setTimestamp($expirationTimestamp);
    }

    public function resendVerificationEmail(string|int $id): void
    {
        $this->asyncMethodDispatcher->dispatch(
            LoggerInterface::class,
            'info',
            [
                'Request to resend verification email',
                [
                    'id' => $id
                ]
            ]
        );
        
        $user = $this->userManager->find($id);
       
        if ($user === null) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'warning',
                [
                    'User not found for verification resend',
                    [
                        'id' => $id
                    ]
                ]
            );

            throw InvalidTokenException::userNotFound();
        }
      // 2. Check if the email is already verified
        if ($user->isEmailVerified()) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'info',
                [
                    'Attempt to resend for an already verified email',
                    [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                    ]
                ]
            );

            throw EmailAlreadyVerifiedException::forEmail($user->getEmail());
        }

        try {
            // The service handles everything: cooldown, generation, hashing, persistence, and event dispatching.
            $this->secureTokenService->generateEmailConfirmationToken($user);
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'info',
                [
                    'Verification email successfully resent.',
                    [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                    ]
                ]
            );

        } catch (\RuntimeException $e) {
            // Le SecureTokenService lève RuntimeException pour le Cool-down.
            // Nous la relançons pour informer l'utilisateur de l'attente.
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'notice',
                [
                    'Token return blocked by cooldown',
                    [
                        'user_id' => $user->getId(),
                        'message' => $e->getMessage(),
                    ]
                ]
            );

            throw $e;
        } catch (\Exception $e) {
            // Log toutes les autres erreurs imprévues (génération, hachage, persistance)
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'error',
                [
                    'Critical failure sending verification email',
                    [
                        'user_id' => $user->getId(),
                        'error' => $e->getMessage(),
                    ]
                ]
            );
           
            // Relancer une RuntimeException générique pour l'utilisateur
            throw new \RuntimeException(
                'Unable to resend verification email. Please try again later.',
                0,
                $e
            );
        }
    }
}
