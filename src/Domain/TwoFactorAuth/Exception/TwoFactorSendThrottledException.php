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
 * Levée quand la limite de renvoi (3 envois / 15 min) est dépassée.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
final class TwoFactorSendThrottledException extends \RuntimeException
{
    public function __construct(string $message = 'Trop de demandes de code. Réessayez dans quelques minutes.')
    {
        parent::__construct($message);
    }
}
