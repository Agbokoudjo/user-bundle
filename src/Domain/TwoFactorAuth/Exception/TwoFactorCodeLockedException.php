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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Exception;

/**
 * Levée quand le nombre maximal de tentatives (5) est atteint.
 * Le déblocage passe par la procédure d'administration existante
 * (pas d'auto-déblocage côté utilisateur).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
final class TwoFactorCodeLockedException extends \RuntimeException
{
    public function __construct(string $message = 'Trop de tentatives incorrectes. Compte verrouillé, contactez un administrateur.')
    {
        parent::__construct($message);
    }
}
