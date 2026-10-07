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
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface;
use Wlindabla\UserBundle\Domain\TwoFactorAuth\Service\TwoFactorCodeServiceInterface;
use Wlindabla\UserBundle\Infrastructure\Persistance\TwoFactorCodeManager;
use Wlindabla\UserBundle\Infrastructure\Service\TwoFactorCodeService;
use Wlindabla\UserBundle\UI\Http\Controller\TwoFactorAuthController;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\TwoFactorAuthListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

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
};