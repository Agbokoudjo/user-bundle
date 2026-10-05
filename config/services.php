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
use Wlindabla\UserBundle\Domain\LoggerLoginUser\Application\Manager\LoggerLoginUserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdaterInterface;
use Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface;
use Wlindabla\UserBundle\Infrastructure\Doctrine\DoctrineUserManager;
use Wlindabla\UserBundle\Infrastructure\Messenger\Handler\RevokeInactiveSessionsHandler;
use Wlindabla\UserBundle\Infrastructure\Persistance\LoggerLoginUserManager;
use Wlindabla\UserBundle\Infrastructure\Persistance\UserSessionManager;
use Wlindabla\UserBundle\Infrastructure\Scheduler\SessionCleanupSchedule;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\UserProvider;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistry;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;
use Wlindabla\UserBundle\UI\Http\Controller\SessionConflictController;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\SessionControlListener;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Service\TwoFactorCodeServiceInterface;
use Wlindabla\UserBundle\Infrastructure\Persistance\TwoFactorCodeManager;
use Wlindabla\UserBundle\Infrastructure\Service\TwoFactorCodeService;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\AdminFormLoginController;
use Wlindabla\UserBundle\UI\Http\Controller\TwoFactorAuthController;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\TwoFactorAuthListener;


return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // -----------------------------------------------------------------
    // Cross-cutting (used by every module)
    // -----------------------------------------------------------------

    $services->set(TemplateRegistry::class)
        ->arg('$templates', '%wlindabla_user.templates%');
    $services->alias(TemplateRegistryInterface::class, TemplateRegistry::class);

    // -----------------------------------------------------------------
    // User module
    // -----------------------------------------------------------------

    $services->set(\Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdater::class)
        ->autowire();
    $services->alias(CanonicalFieldsUpdaterInterface::class, \Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdater::class);

    $services->set(DoctrineUserManager::class)
        ->arg('$userClass', '%wlindabla_user.classes.user%');
    $services->alias(UserManagerInterface::class, DoctrineUserManager::class);

    $services->set(UserProvider::class)
        ->arg('$expiresAfter', '%wlindabla_user.security.user_cache_ttl%');
    $services->alias(\Symfony\Component\Security\Core\User\UserProviderInterface::class, UserProvider::class);
    $services->alias(CacheUserProviderInterface::class, UserProvider::class);


    $services->set(UserSessionManager::class)
        ->arg('$redisSessionPrefix', '%wlindabla_user.session_control.redis_session_prefix%');
    $services->alias(UserSessionManagerInterface::class, UserSessionManager::class);
    
    $services->set(SessionControlListener::class)
        ->arg('$firewallName', '%wlindabla_user.session_control.firewall_name%')
        ->arg('$maxDurationHours', '%wlindabla_user.session_control.max_duration_hours%')
        ->arg('$excludedRoutes', '%wlindabla_user.session_control.excluded_routes%');

    $services->set(SessionConflictController::class)
        ->arg('$kernelEnvironment', '%kernel.environment%')
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->tag('controller.service_arguments');

    // symfony/scheduler is optional — only register these two if it's installed.
    if (interface_exists(\Symfony\Component\Scheduler\ScheduleProviderInterface::class)) {
        $services->set(SessionCleanupSchedule::class)
            ->arg('$inactivityHours', '%wlindabla_user.session_control.inactivity_hours%')
            ->arg('$maxDurationHours', '%wlindabla_user.session_control.max_duration_hours%');

        $services->set(RevokeInactiveSessionsHandler::class);
    }

     // LoggerLoginUser module
    // -----------------------------------------------------------------

    $services->set(LoggerLoginUserManager::class);
    $services->alias(LoggerLoginUserManagerInterface::class, LoggerLoginUserManager::class);

    $services->set(TwoFactorCodeManager::class);
    $services->alias(TwoFactorCodeManagerInterface::class, TwoFactorCodeManager::class);

    $services->set(TwoFactorCodeService::class);
    $services->alias(TwoFactorCodeServiceInterface::class, TwoFactorCodeService::class);

    $services->set(TwoFactorAuthListener::class)
        ->arg('$firewallName', '%wlindabla_user.two_factor.firewall_name%')
        ->arg('$inactivityThresholdSeconds', '%wlindabla_user.two_factor.inactivity_threshold_seconds%')
        ->arg('$absoluteCapSeconds', '%wlindabla_user.two_factor.absolute_cap_seconds%')
        ->arg('$activityTouchThrottleSeconds', '%wlindabla_user.two_factor.activity_touch_throttle_seconds%')
        ->arg('$allowedRoutesWhilePending', '%wlindabla_user.two_factor.allowed_routes_while_pending%');

    $services->set(TwoFactorAuthController::class)
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%')
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->arg('$logoutRouteName', '%wlindabla_user.routes.logout%')
        ->tag('controller.service_arguments');

    $services->set(AdminFormLoginController::class)
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->tag('controller.service_arguments');
};