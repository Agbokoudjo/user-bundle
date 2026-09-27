<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Company: INTERNATIONALES WEB APPS & SERVICES
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\ToggleUserAccountSubscriber;
use Wlindabla\UserBundle\Infrastructure\Messenger\Handler\RevokeInactiveSessionsHandler;
use Wlindabla\UserBundle\Infrastructure\Scheduler\SessionCleanupSchedule;
use Wlindabla\UserBundle\Infrastructure\Validator\Constraint\NotDisposableEmail;
use Wlindabla\UserBundle\Infrastructure\Validator\Constraint\NotDisposableEmailValidator;
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
    
    $services->set(ToggleUserAccountSubscriber::class)
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%')
        ->autowire()
        ->tag('subscriber.service_arguments');
   
    $services
    ->set(NotDisposableEmailValidator::class)
        ->tag('validator.constraint_validator', ['alias' => NotDisposableEmail::class]);

    if (interface_exists(\Symfony\Component\Scheduler\ScheduleProviderInterface::class)) {
        $services
            ->set(SessionCleanupSchedule::class)
                ->arg('$inactivityHours', '%wlindabla_user.session_control.inactivity_hours%')
                ->arg('$maxDurationHours', '%wlindabla_user.session_control.max_duration_hours%')
                ->autowire()
            ->set(RevokeInactiveSessionsHandler::class)
                ->autowire();
    }
};