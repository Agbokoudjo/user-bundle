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

namespace Wlindabla\UserBundle\Domain\EmailChange\Enum;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
enum EmailChangeRequestStatus: string
{
    case Pending = 'pending';
    case AwaitingValidation = 'awaiting_validation';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * A final status can never transition to any other — the same check
     * used throughout this module to reject an action on an already
     * closed request.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Confirmed, self::Cancelled, self::Expired => true,
            self::Pending, self::AwaitingValidation => false,
        };
    }
}