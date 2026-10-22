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

namespace Wlindabla\UserBundle\Domain\TwoFactorAuth\Enum;

/**

* Status of an MFA code.

* PENDING: Generated, awaiting entry.

* VERIFIED: Entered correctly, code used.

* EXPIRED: Validity period expired (10 min).

* LOCKED: Maximum number of attempts reached (5).

* @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>

*/
enum TwoFactorCodeStatus: string
{
    case PENDING  = 'pending';
    case VERIFIED = 'verified';
    case EXPIRED  = 'expired';
    case LOCKED   = 'locked';
}
