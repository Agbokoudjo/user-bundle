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
 * Default, zero-dependency evictor: does nothing. Eviction of the
 * underlying session-storage entry is a hygiene optimization, never the
 * security boundary — that boundary is SessionControlListener's DB check on
 * the device's next request. Host applications opt in to immediate,
 * storage-level eviction by aliasing SessionStoreEvictorInterface to a
 * backend-specific adapter in their own services.yaml.
 */
final class NullSessionStoreEvictor implements SessionStoreEvictorInterface
{
    public function evict(string $sessionId): void
    {
        // Intentional no-op.
    }
}