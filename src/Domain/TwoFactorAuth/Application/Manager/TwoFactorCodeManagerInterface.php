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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager;

use App\WlindablaUserBundle\Domain\TwoFactorAuth\Exception\InvalidTwoFactorCodeException;
use App\WlindablaUserBundle\Domain\TwoFactorAuth\Exception\TwoFactorCodeLockedException;
use App\WlindablaUserBundle\Domain\TwoFactorAuth\Exception\TwoFactorSendThrottledException;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Model\TwoFactorCodeInterface;

/**
 * Contrat du service d'orchestration du MFA par email.
 *
 * Les mutations (generateAndSend, verify) sont synchrones : le contrôleur
 * a besoin du résultat immédiatement. Seul l'envoi de l'email est délégué
 * en tâche async par le Mailer (cf SystemMailer::send -> sendAsync).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface TwoFactorCodeManagerInterface
{
    /**
     * Génère un nouveau code, invalide les codes PENDING précédents de
     * l'utilisateur, persiste le hash et déclenche l'envoi de l'email.
     *
     * @throws TwoFactorSendThrottledException
     *         si la fenêtre de renvoi (3 / 15 min) est dépassée.
     */
    public function generateAndSend(string $userIdentifier, ?string $ipAddress, string $context = 'login'): ?TwoFactorCodeInterface;

    /**
     * Vérifie le code saisi pour l'utilisateur donné.
     *
     * @throws InvalidTwoFactorCodeException
     * @throws TwoFactorCodeLockedException
     */
    public function verify(string $userIdentifier, string $submittedCode): void;

    /**
     * Retourne le code PENDING actif de l'utilisateur, ou null.
     */
    public function findActivePending(string $userIdentifier): ?TwoFactorCodeInterface;

    /**
     * Invalide (EXPIRED) tous les codes PENDING d'un utilisateur.
     * Utilisé à la déconnexion et lors d'une nouvelle demande de code.
     */
    public function invalidatePending(string $userIdentifier): void;
}
