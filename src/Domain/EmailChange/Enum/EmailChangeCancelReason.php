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
enum EmailChangeCancelReason: string
{
    /** The old-email holder reported the request via the "not me" link. */
    case NOT_ME = 'not_me';

    /** A validator explicitly rejected the request during double validation. */
    case REJECTED_BY_VALIDATOR = 'rejected_by_validator';

    /** Neither confirmation (and, if required, validation) completed before expiresAt. */
    case EXPIRED = 'expired';
}