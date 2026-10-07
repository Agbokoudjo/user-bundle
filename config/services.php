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
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdater;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdaterInterface;
use Wlindabla\UserBundle\Infrastructure\Doctrine\DoctrineUserManager;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\UserProvider;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistry;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\AdminFormLoginController;
use Wlindabla\UserBundle\UI\Http\Controller\EmailVerificationController;
use Wlindabla\UserBundle\UI\Http\Controller\PasswordResetConfirmationController;
use Wlindabla\UserBundle\UI\Http\Controller\PasswordResetRequestController;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\AccessDeniedListener;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\EntityInUseExceptionListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(TemplateRegistry::class)
        ->arg('$templates', '%wlindabla_user.templates%');
    $services->alias(TemplateRegistryInterface::class, TemplateRegistry::class);

    $services->set(CanonicalFieldsUpdater::class);
    $services->alias(CanonicalFieldsUpdaterInterface::class, CanonicalFieldsUpdater::class);

    $services->set(DoctrineUserManager::class)
        ->arg('$userClass', '%wlindabla_user.classes.user%');
    $services->alias(UserManagerInterface::class, DoctrineUserManager::class);

    $services->set(UserProvider::class)
        ->arg('$expiresAfter', '%wlindabla_user.security.user_cache_ttl%');
    $services->alias(\Symfony\Component\Security\Core\User\UserProviderInterface::class, UserProvider::class);
    $services->alias(CacheUserProviderInterface::class, UserProvider::class);

    $services->set(AdminFormLoginController::class)
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->arg('$siteName', '%app.site_name%')
        ->tag('controller.service_arguments');

    $services->set(EmailVerificationController::class)
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%')
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->tag('controller.service_arguments');

    $services->set(PasswordResetRequestController::class)
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->tag('controller.service_arguments');

    $services->set(PasswordResetConfirmationController::class)
        ->tag('controller.service_arguments');

    $services->set(AccessDeniedListener::class);

    $services->set(EntityInUseExceptionListener::class);
};