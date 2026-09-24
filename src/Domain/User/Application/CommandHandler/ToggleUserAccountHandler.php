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

namespace App\WlindablaUserBundle\Domain\User\Application\CommandHandler;

use Wlindabla\ShareServiceBundle\Bus\EventBusInterface;
use Wlindabla\ShareServiceBundle\Security\Generator\GenerateTemporaryPasswordService;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Enum\AccountStatus;
use Wlindabla\UserBundle\Domain\User\Event\ToggleUserAccountEvent;

/**
 * Use case pour l'activation ou la désactivation d'un compte utilisateur.
 * 
 * Responsabilités :
 * - Rechercher l'utilisateur par son username/email
 * - Générer un nouveau mot de passe lors de l'activation
 * - Mettre à jour le statut du compte
 * - Déclencher un événement de domaine pour notifications
 * 
 * Note : Le mot de passe n'est généré que lors de l'ACTIVATION,
 * pas lors de la désactivation.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Application\UseCase\CommandHandler\User
 */
final class ToggleUserAccountHandler
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
        private readonly EventBusInterface $eventBus,
        private readonly GenerateTemporaryPasswordService $generateTemporaryPassword
    ) {}

    /**
     * Active ou désactive un compte utilisateur.
     * 
     * Logique d'activation :
     * - Si c'est la PREMIÈRE ACTIVATION (pas de mot de passe) :
     *   → Génère un mot de passe temporaire fort
     *   → L'utilisateur le recevra par email
     *   → Il devra le changer à sa première connexion
     * 
     * - Si c'est une RÉACTIVATION (mot de passe existant) :
     *   → Le mot de passe existant est conservé
     *   → L'utilisateur peut se connecter directement
     * 
     * Logique de désactivation :
     * - Le compte est désactivé
     * - Le mot de passe existant est conservé
     *
     * @param string|int $userId L'identifiant de l'utilisateur (id)
     * @param AccountStatus $status Le nouveau statut du compte
     * 
     * @return void
     *
     */
    public function handle(
        string|int $userIdOrUsername,
        AccountStatus $status = AccountStatus::ACTIVE
    ): void
    {
        try {
            if (is_int($userIdOrUsername) || ctype_digit($userIdOrUsername)) {
                $user = $this->userManager->find((int) $userIdOrUsername);
            } else {
                $user = $this->userManager->findUserByUsernameOrEmail($userIdOrUsername);
            }

            if ($user === null) {
                return;
            }

            // 4. Vérification si le statut change réellement
            if ($user->isEnabled() === $status->toBool()) {
                // Pas de changement nécessaire
                return;
            }

            // 5. Application du changement de statut
            $plainPasswordTemporary = null;
            /**
             * Gère la logique spécifique au mot de passe lors de l'activation (l'entité est passée à 'enabled').
             * * Cette logique assure l'intégrité des données et la sécurité concernant le mot de passe :
             * * 1. Nouvel Utilisateur (Première Activation) :
             * - SI l'utilisateur n'a PAS de mot de passe haché (c'est-à-dire que c'est la première activation),
             * un nouveau mot de passe sécurisé DOIT être généré et enregistré.
             * * 2. Utilisateur Existant :
             * - SI l'utilisateur possède DÉJÀ un mot de passe haché, AUCUN nouveau mot de passe n'est généré.
             * Le mot de passe existant DOIT être conservé pour maintenir l'accès de l'utilisateur.
             * */
            if ($status === AccountStatus::ACTIVE && !$user->hasPassword()) {
                // Activation : générer un nouveau mot de passe temporaire
                $plainPasswordTemporary = $this->generateTemporaryPassword->generateTemporaryPassword();
                $user->setPlainPassword($plainPasswordTemporary);
            }

            // Mettre à jour le statut du compte
            $user->setEnabled($status->toBool());
            $this->userManager->updatePassword($user);
            $user->setUpdatedAt(new \DateTime('now', new \DateTimeZone("UTC")));
            //  Persistance des changements
            $this->userManager->save($user);

            //  Déclenchement de l'événement de domaine
            $this->eventBus->dispatch(
                new ToggleUserAccountEvent($user, $plainPasswordTemporary)
            );
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
