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

namespace Wlindabla\UserBundle\Infrastructure\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Wlindabla\UserBundle\Infrastructure\Messenger\Message\RevokeInactiveSessionsMessage;

/**
 * Every 15 minutes, revokes sessions past the configured inactivity
 * threshold or absolute max duration (wlindabla_user.session_control.*).
 *
 * Requires symfony/scheduler (optional dependency — see this bundle's
 * composer.json "suggest"). FrankenPHP runs the Scheduler natively in
 * worker mode; no external cron is needed.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
#[AsSchedule('wlindabla_user_session_cleanup')]
final class SessionCleanupSchedule implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $inactivityHours,
        private readonly int $maxDurationHours,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->with(
                RecurringMessage::every(
                    '15 minutes',
                    new RevokeInactiveSessionsMessage($this->inactivityHours, $this->maxDurationHours),
                ),
            )
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}