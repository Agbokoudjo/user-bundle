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
 * Levée quand le code saisi est incorrect ou expiré.
 * Le message reste volontairement générique (jamais de détail sur la cause
 * exacte : évite de révéler à un attaquant s'il approche du bon code).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
final class InvalidTwoFactorCodeException extends \RuntimeException
{
    public function __construct(string $message = 'Code invalide ou expiré.')
    {
        parent::__construct($message);
    }
}
