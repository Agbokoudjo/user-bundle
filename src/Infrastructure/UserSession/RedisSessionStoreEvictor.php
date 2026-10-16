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

namespace Wlindabla\UserBundle\Infrastructure\UserSession;

use Wlindabla\UserBundle\Domain\UserSession\Application\Service\SessionStoreEvictorInterface;

/**
 * Talks directly to the Redis client — NEVER through
 * \SessionHandlerInterface::destroy(), whose cookie-clearing side effects
 * assume it's only ever called for the current request's own session (see
 * AbstractSessionHandler::destroy()). Calling destroy() on a foreign
 * session ID can clear the Set-Cookie header of the request that's actually
 * revoking another device's session. This class only touches the storage
 * key, mirroring what RedisSessionHandler::doDestroy() does internally.
 *
 * Not auto-registered — alias SessionStoreEvictorInterface to this class in
 * your own services.yaml, with the same $redis service and prefix your
 * RedisSessionHandler already uses.
 */
final class RedisSessionStoreEvictor implements SessionStoreEvictorInterface
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $prefix = 'sf_wlindabla_user_s',
    ) {
    }

    public function evict(string $sessionId): void
    {
        $this->redis->unlink($this->prefix.$sessionId);
    }
}