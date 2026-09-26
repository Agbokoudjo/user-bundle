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
 * Gère le flux "mot de passe oublié" de bout en bout.
 *
 * Pour des raisons de sécurité (utilisateurs finaux issus du secteur de la santé),
 * aucune saisie de nouveau mot de passe n'est autorisée directement depuis le lien
 * email : la confirmation déclenche uniquement la génération d'un mot de passe
 * temporaire fort, envoyé par email, que l'utilisateur pourra changer une fois
 * connecté.
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
        private LoggerInterface $logger,
    ) {}

    public function requestReset(string $usernameOrEmail): void
    {
        $user = $this->userManager->findUserByUsernameOrEmail($usernameOrEmail);

        if (null === $user) {
            // Ne jamais révéler qu'aucun compte ne correspond (anti-énumération).
            $this->logger->info('Demande de reset password pour un identifiant inconnu.', [
                'identifier' => $usernameOrEmail,
            ]);

            return;
        }

        if (!$user->isEnabled() || !$user->isAccountNonLocked()) {
            $this->logger->info('Demande de reset password ignorée : compte désactivé ou verrouillé.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        // Anti-spam : on ne régénère pas de token tant que le précédent est encore valide.
        if ($user->isPasswordRequestNonExpired(self::RETRY_COOLDOWN)) {
            $this->logger->notice('Demande de reset password ignorée : cooldown actif.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $plainToken = $this->tokenGenerator->generate(TokenGeneratorInterface::DEFAULT_PASSWORD_RESET_TOKEN_LENGTH);
        $hashedToken = $this->tokenHasher->hash($plainToken);

        $user->setConfirmationToken($hashedToken);
        $user->setPasswordRequestedAt(new \DateTime('now', new \DateTimeZone('UTC')));

        $this->userManager->save($user);

        $this->asyncMethodDispatcher->dispatch(
            PasswordResetMailerService::class,
            'sendResetRequestEmail',
            [$user->getId(), $plainToken]
        );

        $this->logger->info('Demande de reset password acceptée, email en cours d\'envoi.', [
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
            $this->logger->error('Tentative de reset password avec un token invalide (hash mismatch).', [
                'user_id' => $user->getId(),
            ]);

            throw InvalidTokenException::invalidToken();
        }

        // Le token a rempli son rôle : on le consomme immédiatement pour empêcher
        // toute réutilisation, avant même que le mot de passe temporaire soit généré.
        $user->setConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $this->userManager->save($user);

        $this->asyncMethodDispatcher->dispatch(
            GenerateTemporaryPasswordHandler::class,
            'process',
            [$user->getId()]
        );

        $this->logger->info('Lien de reset password confirmé, génération du mot de passe temporaire déclenchée.', [
            'user_id' => $user->getId(),
        ]);
    }
}
