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
 * Service de vérification des emails utilisateurs.
 * 
 * Gère la vérification des tokens de confirmation d'email envoyés aux utilisateurs
 * lors de leur inscription. Vérifie l'expiration et la validité du token avant
 * d'activer le compte.
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
                'Tentative de vérification d\'email',
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
                    'Utilisateur non trouvé pour la vérification d\'email',
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
                ['Token manquant ou compte déjà vérifié', [
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
                    'Token de vérification expiré',
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
                ['Token de vérification invalide (hash mismatch)', ['user_id' => $user->getId()]],
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

        // 7. Application des changements (logique métier) en asynchrone
        // Application synchrone : referme la fenêtre entre "token validé" et
        // "token invalidé" — un deuxième passage sur le même lien pendant cette
        // fenêtre (double-clic, prévisualisation d'un client mail, retry réseau)
        // ne doit plus pouvoir revalider un token déjà consommé. Le log et
        // l'événement d'envoi d'email, eux, restent asynchrones : c'est là que
        // l'async apporte quelque chose (décharger la base et le SMTP sous forte
        // charge), pas sur l'écriture qui décide si le lien reste valide.
        $this->applyEmailVerificationHandler->handle($user->getId());

        $this->asyncMethodDispatcher->dispatch(
            LoggerInterface::class,
            'info',
            [
                'Email vérifié avec succès',
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
     * Calcule la date d'expiration d'un token.
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
                'Demande de renvoi d\'email de vérification',
                [
                    'id' => $id
                ]
            ]
        );
        
        // 1. Récupération de l'utilisateur
        $user = $this->userManager->find($id);
       
        if ($user === null) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'warning',
                [
                    'Utilisateur non trouvé pour renvoi de vérification',
                    [
                        'id' => $id
                    ]
                ]
            );

            throw InvalidTokenException::userNotFound();
        }
        // 2. Vérification si l'email est déjà vérifié
        if ($user->isEmailVerified()) {
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'info',
                [
                    'Tentative de renvoi pour email déjà vérifié',
                    [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                    ]
                ]
            );

            throw EmailAlreadyVerifiedException::forEmail($user->getEmail());
        }

        try {
            // Le service s'occupe de tout : cooldown, génération, hachage, persistance et dispatch de l'événement.
            $this->secureTokenService->generateEmailConfirmationToken($user);
            $this->asyncMethodDispatcher->dispatch(
                LoggerInterface::class,
                'info',
                [
                    'Email de vérification renvoyé avec succès',
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
                    'Renvoi de token bloqué par le cooldown',
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
                    'Échec critique du renvoi d\'email de vérification',
                    [
                        'user_id' => $user->getId(),
                        'error' => $e->getMessage(),
                    ]
                ]
            );
           
            // Relancer une RuntimeException générique pour l'utilisateur
            throw new \RuntimeException(
                'Impossible de renvoyer l\'email de vérification. Veuillez réessayer plus tard.',
                0,
                $e
            );
        }
    }
}
