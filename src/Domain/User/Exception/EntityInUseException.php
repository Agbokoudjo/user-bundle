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

namespace Wlindabla\UserBundle\Domain\User\Exception;

/**
 * Thrown when an entity cannot be deleted (or otherwise modified) because
 * it is still referenced elsewhere — a business/integrity block, never an
 * authorization question. Deliberately not an InvalidTokenException or an
 * AccessDeniedException: those answer "are you allowed to do this?", this
 * one answers "the system can't do this right now, regardless of who's
 * asking".
 *
 * The message is meant to always be shown to the user as-is, even in
 * production — it is never a technical information leak, just a useful
 * explanation ("still assigned to 3 people").
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
class EntityInUseException extends \RuntimeException
{
}