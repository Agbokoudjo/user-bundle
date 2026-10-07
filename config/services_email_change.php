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
use Wlindabla\UserBundle\Domain\EmailChange\Application\Manager\EmailChangeRequestServiceInterface;
use Wlindabla\UserBundle\Infrastructure\Persistance\EmailChangeRequestService;
use Wlindabla\UserBundle\Infrastructure\Service\EmailChangeRequestMailerService;
use Wlindabla\UserBundle\UI\Http\Controller\EmailChangeConfirmationController;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(EmailChangeRequestService::class)
        ->arg('$tokenLifetimeSeconds', '%wlindabla_user.email_change.token_lifetime%')
        ->arg('$retryCooldownSeconds', '%wlindabla_user.email_change.retry_cooldown%')
        ->arg('$exemptRolesOnSelf', '%wlindabla_user.email_change.exempt_roles_on_self%')
        ->arg('$recoveryInitiatorRole', '%wlindabla_user.email_change.recovery_initiator_role%');
    $services->alias(EmailChangeRequestServiceInterface::class, EmailChangeRequestService::class);

    $services->set(EmailChangeRequestMailerService::class);

    $services->set(EmailChangeConfirmationController::class);
};