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

use Wlindabla\UserBundle\UI\Console\ResetUserPasswordCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\ApplyEmailVerificationHandler;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\GenerateTemporaryPasswordHandler;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\ToggleUserAccountHandler;
use Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler;
use Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdater;
use Wlindabla\UserBundle\Domain\User\Service\CanonicalFieldsUpdaterInterface;
use Wlindabla\UserBundle\Domain\User\Service\EmailVerificationInterface;
use Wlindabla\UserBundle\Domain\User\Service\PasswordResetInterface;
use Wlindabla\UserBundle\Domain\User\Service\UpdatePasswordUserServiceInterface;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\DoctrineBlameableListener;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\DoctrineTimestampableListener;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\DoctrineUserEventListener;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\ToggleUserAccountSubscriber;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\UpdatePasswordUserSubscriber;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\UpdateProfileSuccessListener;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\UserAccountEmailVerificationSubscriber;
use Wlindabla\UserBundle\Infrastructure\EventSubscriber\UserIdentityNormalizerDoctrineLifecycleListener;
use Wlindabla\UserBundle\Infrastructure\Form\Type\PasswordResetRequestType;
use Wlindabla\UserBundle\Infrastructure\Persistance\DoctrineUserManager;
use Wlindabla\UserBundle\Infrastructure\Persistance\UserManagerDoctrineReference;
use Wlindabla\UserBundle\Infrastructure\Routing\WlindablaRouteLoader;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\CacheUserProviderInterface;
use Wlindabla\UserBundle\Infrastructure\Security\Provider\UserProvider;
use Wlindabla\UserBundle\Infrastructure\Security\SecurityContextInterface;
use Wlindabla\UserBundle\Infrastructure\Security\SymfonySecurityContextAdapter;
use Wlindabla\UserBundle\Infrastructure\Service\EmailVerificationService;
use Wlindabla\UserBundle\Infrastructure\Service\PasswordResetMailerService;
use Wlindabla\UserBundle\Infrastructure\Service\PasswordResetService;
use Wlindabla\UserBundle\Infrastructure\Service\SecureTokenService;
use Wlindabla\UserBundle\Infrastructure\Service\UpdatePasswordUserService;
use Wlindabla\UserBundle\Infrastructure\Service\UserDuplicateFinderService;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistry;
use Wlindabla\UserBundle\Infrastructure\Templating\TemplateRegistryInterface;
use Wlindabla\UserBundle\UI\Console\CreateAdminUserCommand;
use Wlindabla\UserBundle\UI\Console\GenerateTemporaryPasswordCommand;
use Wlindabla\UserBundle\UI\Console\ToggleAdminUserAccountCommand;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\AdminFormLoginController;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\UserLogoutController;
use Wlindabla\UserBundle\UI\Http\Controller\EmailVerificationController;
use Wlindabla\UserBundle\UI\Http\Controller\OutcomePageRenderer;
use Wlindabla\UserBundle\UI\Http\Controller\PasswordResetConfirmationController;
use Wlindabla\UserBundle\UI\Http\Controller\PasswordResetRequestController;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\AccessDeniedSubscriber;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\EntityInUseExceptionListener;
use Wlindabla\UserBundle\UI\Http\EventSubscriber\LoginSubscriber;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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
    $services->set(UserDuplicateFinderService::class);

    $services->set(DoctrineUserManager::class);
    $services->alias(UserManagerInterface::class, DoctrineUserManager::class);
    $services->alias(UserManagerDoctrineReference::class, DoctrineUserManager::class);

    $services->set(UserProvider::class)
        ->arg('$expiresAfter', '%wlindabla_user.security.user_cache_ttl%');
    $services->alias(\Symfony\Component\Security\Core\User\UserProviderInterface::class, UserProvider::class);
    $services->alias(CacheUserProviderInterface::class, UserProvider::class);

    // Resolves %parameters% in route names, then routes the enabled modules.
    // Imported by config/routes.php with: $routes->import('.', WlindablaRouteLoader::TYPE)
    $services->set(WlindablaRouteLoader::class)
        ->args([service('parameter_bag'), param('kernel.environment')])
        ->tag('routing.loader');

    $services->set(AdminFormLoginController::class)
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->arg('$dashboardRouteName', '%wlindabla_user.routes.dashboard%')
        ->arg('$siteName', '%app.site_name%')
        ->tag('controller.service_arguments');

    $services->set(UserLogoutController::class);

    $services->set(EmailVerificationController::class)
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%')
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->tag('controller.service_arguments');

    $services->set(PasswordResetRequestController::class)
        ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%')
        ->tag('controller.service_arguments');

    $services->set(PasswordResetConfirmationController::class)
        ->tag('controller.service_arguments');

    $services->set(AccessDeniedSubscriber::class);

    $services->set(EntityInUseExceptionListener::class);

    $services->set(EmailVerificationInterface::class,EmailVerificationService::class);

    $services->set(PasswordResetInterface::class,PasswordResetService::class);
    $services->set(PasswordResetMailerService::class);
    
    $services->set(UpdatePasswordUserServiceInterface::class,UpdatePasswordUserService::class);

    $services->set(OutcomePageRenderer::class)
            ->arg('$loginRouteName', '%wlindabla_user.routes.login%')
            ->arg('$homeRouteName', '%wlindabla_user.routes.home%')
            ->arg('$siteName', '%app.site_name%')
            ;

    $services->set(SecureTokenService::class);

    $services->set(ApplyEmailVerificationHandler::class);
    $services->set(UpdateUserProfileHandler::class);
    $services->set(GenerateTemporaryPasswordHandler::class);
    $services->set(ToggleUserAccountHandler::class);

    //Console
    $services->set(CreateAdminUserCommand::class);
    $services->set(GenerateTemporaryPasswordCommand::class);
    $services->set(ResetUserPasswordCommand::class);
    $services->set(ToggleAdminUserAccountCommand::class);

    // Event subscribers (notification emails) — must be declared explicitly:
    // autoconfigure() only tags services that already exist in the container,
    // it never scans/discovers classes on its own.
    $services->set(UpdatePasswordUserSubscriber::class)
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%');

    $services->set(ToggleUserAccountSubscriber::class)
        ->arg('$loginRouteName', '%wlindabla_user.routes.login%');

    $services->set(UserAccountEmailVerificationSubscriber::class);
    $services->set(DoctrineBlameableListener::class);
    $services->set(DoctrineTimestampableListener::class);
    $services->set(DoctrineUserEventListener::class);
    $services->set(UserIdentityNormalizerDoctrineLifecycleListener::class);
    $services->set(UpdateProfileSuccessListener::class);
    $services->set(LoginSubscriber::class);

    $services->set(SymfonySecurityContextAdapter::class);
    $services->alias(SecurityContextInterface::class, SymfonySecurityContextAdapter::class);
    $services->set(PasswordResetRequestType::class);
};