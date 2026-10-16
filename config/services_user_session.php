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

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Domain\UserSession\Application\Service\SessionStoreEvictorInterface;
use Wlindabla\UserBundle\Infrastructure\Messenger\Handler\RevokeInactiveSessionsHandler;
use Wlindabla\UserBundle\Infrastructure\Persistance\UserSessionManager;
use Wlindabla\UserBundle\Infrastructure\Scheduler\SessionCleanupSchedule;
use Wlindabla\UserBundle\Infrastructure\UserSession\NullSessionStoreEvictor;
use Wlindabla\UserBundle\UI\Console\RevokeAllUserSessionsCommand;
use Wlindabla\UserBundle\UI\Console\RevokeUserSessionCommand;
use Wlindabla\UserBundle\UI\Http\Controller\SessionConflictController;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\SessionControlListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(UserSessionManager::class);
    $services->alias(UserSessionManagerInterface::class, UserSessionManager::class);

    $services->set(SessionControlListener::class)
        ->arg('$firewallName', '%wlindabla_user.session_control.firewall_name%')
        ->arg('$maxDurationHours', '%wlindabla_user.session_control.max_duration_hours%')
        ->arg('$excludedRoutes', '%wlindabla_user.session_control.excluded_routes%')
        ->arg('$sessionPolicy', '%wlindabla_user.session_control.policy%');

    $services->set(SessionConflictController::class)
        ->arg('$kernelEnvironment', '%kernel.environment%')
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->arg('$logoutRouteName', '%wlindabla_user.routes.logout%')
        ->arg('$siteName', '%app.site_name%');

    $services->set(NullSessionStoreEvictor::class);
    $services->alias(SessionStoreEvictorInterface::class, NullSessionStoreEvictor::class);

    $services->set(RevokeUserSessionCommand::class);
    $services->set(RevokeAllUserSessionsCommand::class);
    
    if (interface_exists(\Symfony\Component\Scheduler\ScheduleProviderInterface::class)) {
        $services->set(SessionCleanupSchedule::class)
            ->arg('$inactivityHours', '%wlindabla_user.session_control.inactivity_hours%')
            ->arg('$maxDurationHours', '%wlindabla_user.session_control.max_duration_hours%');

        $services->set(RevokeInactiveSessionsHandler::class);
    }
};