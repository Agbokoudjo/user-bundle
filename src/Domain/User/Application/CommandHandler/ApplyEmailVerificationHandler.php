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

namespace Wlindabla\UserBundle\Domain\User\Application\CommandHandler;

use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Model\BaseUserInterface;

/**
 * Applique les changements de vérification d'email à l'utilisateur.
 *
 */
final readonly class ApplyEmailVerificationHandler
{
    public function __construct(
        private UserManagerInterface $userManager
    ) {}
    
    /**
     * @param string|int $userId de L'utilisateur dont l'email a été vérifié
     * @return void
     */
    public function handle(
        string |int $userId, 
        ):void{
        
       try {
            /**
             * @var BaseUserInterface|null $user
             */
            $user = $this->userManager->find($userId);

            if (!$user) {
                return;
            }

            // Marquer l'email comme vérifié
            $user->setEmailVerified(true);
            $user->setEmailVerifiedAt(new \DateTimeImmutable('now', new \DateTimeZone("UTC")));

            // Nettoyer les données de token
            $user->setConfirmationToken(null);
            $user->setTokenRequestedAt(null);
            $user->setUpdatedAt(new \DateTime('now', new \DateTimeZone("UTC")));
            // Persistance des changements
            $this->userManager->save($user);
       } catch (\Throwable $th) {
          throw $th;
       }
    }
}
