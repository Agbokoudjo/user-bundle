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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Service;

/**
 * Contrat du service d'orchestration du MFA par email.
 *
 *l'envoi de l'email est délégué
 * en tâche async par le Mailer (cf SystemMailer::send -> sendAsync).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
interface TwoFactorCodeServiceInterface
{
    /**
     * Génère un nouveau code, invalide les codes PENDING précédents de
     * l'utilisateur, persiste le hash et déclenche l'envoi de l'email.
     *
     */
    public function sendCodeByEmail(
        string $userIdentifier, 
        string $plainCode, 
        string $context,
        string $siteName,
        ?string $ipAddress): void ;

}
