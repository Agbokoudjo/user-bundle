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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface ;
use Wlindabla\ShareServiceBundle\Security\Generator\TokenGeneratorInterface;
use Wlindabla\ShareServiceBundle\Security\Hash\TokenHasherInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Event\UserAccountEmailVerificationEvent;
use Wlindabla\UserBundle\Domain\User\Exception\EmailAlreadyVerifiedException;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Application\Service
 */
final class SecureTokenService
{
    /**
     * Délai minimum entre deux générations de token (en secondes).
     * Protège contre les abus et le spam.
     */
    private const TOKEN_GENERATION_COOLDOWN = 60;
    
    public function __construct(
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly EventDispatcherInterface $eventBusDispatcher,
        private readonly UserManagerInterface $userManager,
    ) {}

    /**
     * Génère et stocke un nouveau token de confirmation d'email.
     * 
     * Cette méthode :
     * 1. Valide l'utilisateur et ses données
     * 2. Génère un token cryptographiquement sécurisé
     * 3. Hashe le token pour stockage sécurisé
     * 4. Persiste les changements en base de données
     * 5. Déclenche un événement pour l'envoi d'email
     * 
     * Le token en clair est envoyé dans l'événement pour être inclus
     * dans l'email de vérification. Seul le hash est stocké en BDD.
     *
     * @param BaseUserInterface $user L'utilisateur pour lequel générer le token
     * @param int $length La longueur du token (par défaut 32 caractères)
     * 
     * @return void
     * 
     * @throws InvalidArgumentException Si l'utilisateur ou la longueur sont invalides
     * @throws RuntimeException Si la génération ou le stockage échoue
     * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
     * @package App\Application\Service
     */
    public function generateEmailConfirmationToken(
        BaseUserInterface $user,
        int $length=TokenGeneratorInterface::DEFAULT_EMAIL_TOKEN_LENGTH
        ): void
    {
        try {
            // 1. Validation des données utilisateur
            $this->validateUser($user);
           
            // 2. Vérification du rate limiting
            $this->enforceTokenGenerationCooldown($user);
            // 3. Génération du token en clair (cryptographiquement sécurisé)
            $plainToken = $this->tokenGenerator->generate($length);
            // 4. Hachage du token pour stockage sécurisé
            $hashedToken = $this->tokenHasher->hash($plainToken);
            // 5. Mise à jour de l'entité utilisateur
            $user->setConfirmationToken($hashedToken);
            $user->setTokenRequestedAt(new \DateTime("now",new \DateTimeZone('UTC'))); //Car le type est date_time en BDD

            // 6. Persistance en base de données
            $this->userManager->save($user);
            
            // 7. Déclenchement de l'événement pour envoi d'email
            $event = new UserAccountEmailVerificationEvent(
                $user->getUsername(),
                $user->getEmail(),
                $user->getId(),
                $plainToken
            );
            $this->eventBusDispatcher->dispatch($event);

        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * Valide que l'utilisateur peut recevoir un token de confirmation.
     * La couche de presentation fait deja ce qui est validation de l'utilisateur
     * mais on le fait quand meme
     * @param BaseUserInterface $user L'utilisateur à valider
     * 
     * @throws InvalidArgumentException Si l'utilisateur est invalide
     */
    private function validateUser(BaseUserInterface $user): void
    {
        if (empty($user->getEmail())) {
            throw new InvalidArgumentException(
                'L\'utilisateur doit avoir une adresse email valide pour recevoir un token de confirmation.'
            );
        }

        if ($user->isEmailVerified()) {
            
            throw EmailAlreadyVerifiedException::forEmail($user->getEmail());
        }
    }

    /**
     * Applique un rate limiting sur la génération de tokens.
     * 
     * Empêche les abus en limitant la fréquence de génération de tokens.
     *
     * @param BaseUserInterface $user L'utilisateur demandant un token
     * 
     * @throws RuntimeException Si le délai minimum n'est pas respecté
     */
    private function enforceTokenGenerationCooldown(BaseUserInterface $user): void
    {
        $lastRequestedAt = $user->getTokenRequestedAt();

        if ($lastRequestedAt === null) {
            // Première demande, OK
            return;
        }

        $now = new \DateTimeImmutable();
        $timeSinceLastRequest = $now->getTimestamp() - $lastRequestedAt->getTimestamp();

        if ($timeSinceLastRequest < self::TOKEN_GENERATION_COOLDOWN) {
            $remainingTime = self::TOKEN_GENERATION_COOLDOWN - $timeSinceLastRequest;

            throw new RuntimeException(
                \sprintf(
                    'Veuillez patienter %d secondes avant de demander un nouveau lien de vérification.',
                    $remainingTime
                )
            );
        }
    }
}
