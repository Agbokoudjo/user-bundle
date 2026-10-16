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

namespace Wlindabla\UserBundle\Domain\UserSession\Application\Service;

/**
 * Evicts the underlying session-storage entry (Redis, PDO, Memcached,
 * native files...) for a given PHP session ID — independent from the
 * application-level revocation already recorded via UserSessionManager.
 * A no-op is a safe, acceptable outcome when no handler is configured
 * (native file sessions with framework.session.handler_id left unset).
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package Wlindabla\UserBundle\Domain\UserSession\Application\Service
 */
interface SessionStoreEvictorInterface
{
    public function evict(string $sessionId): void;
}