<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Company: INTERNATIONALES WEB APPS & SERVICES
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\AdminFormLoginController;
use Wlindabla\UserBundle\UI\Http\Controller\EmailVerificationController;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();
    
    $services->set(EmailVerificationController::class)
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%')
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->arg('$siteName', '%app.site_name%')
        ->autowire()
        ->tag('controller.service_arguments');
    
    $services->set(AdminFormLoginController::class)
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->arg('$siteName', '%app.site_name%')
        ->autowire()
        ->tag('controller.service_arguments');
};