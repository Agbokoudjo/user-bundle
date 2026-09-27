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

namespace Wlindabla\UserBundle\Domain\UserSession\Enum;

/**
 * Reason a user session was revoked. Lets SessionControlListener choose
 * the right message/redirect when it later finds, on the next request,
 * that this browser's session has been revoked in the meantime.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
enum UserSessionRevokedReason: string
{
    case CONFLICT = 'conflict';
    case EXPIRED_INACTIVITY = 'expired_inactivity';
    case EXPIRED_MAX_DURATION = 'expired_max_duration';
    case MANUAL_LOGOUT = 'manual_logout';
    case SECURITY_EMAIL_CHANGED = 'security_email_changed';

    /**
     * Translation key for the flash message shown on the login page for
     * this reason. The domain layer never returns a display string
     * directly — the message text lives in translations/, not here.
     */
    public function getTranslationKey(): string
    {
        return match ($this) {
            self::CONFLICT => 'session_revoked.conflict',
            self::EXPIRED_INACTIVITY => 'session_revoked.expired_inactivity',
            self::EXPIRED_MAX_DURATION => 'session_revoked.expired_max_duration',
            self::MANUAL_LOGOUT => 'session_revoked.manual_logout',
            self::SECURITY_EMAIL_CHANGED => 'session_revoked.security_email_changed',
        };
    }
}