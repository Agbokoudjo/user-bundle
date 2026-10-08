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
namespace Wlindabla\UserBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeRequestInterface;
use Wlindabla\UserBundle\Domain\EmailChange\Model\EmailChangeValidationInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\PermissionGroupInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\PermissionRoleInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\UserPermissionGroupInterface;
use Wlindabla\UserBundle\Domain\Permission\Model\UserPermissionRoleInterface;
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;
use Wlindabla\UserBundle\Infrastructure\Routing\WlindablaRouteLoader;

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class WlindablaUserBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->arrayNode('classes')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('user')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('user_token')->defaultNull()->end()
                    ->scalarNode('user_session')->defaultNull()->end()
                    ->scalarNode('logger_login_user')->defaultNull()->end()
                    ->scalarNode('two_factor_code')->defaultNull()->end()
                    ->scalarNode('permission_role')->defaultNull()->end()
                    ->scalarNode('permission_group')->defaultNull()->end()
                    ->scalarNode('user_permission_role')->defaultNull()->end()
                    ->scalarNode('user_permission_group')->defaultNull()->end()
                    ->scalarNode('email_change_request')->defaultNull()->end()
                    ->scalarNode('email_change_validation')->defaultNull()->end()
                ->end()
            ->end()
            ->scalarNode('default_role')
                ->info('Role added to every user. Never use ROLE_ADMIN here.')
                ->defaultValue('ROLE_USER')
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('security')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('user_cache_ttl')->defaultValue(43200)->min(0)->end()
                ->end()
            ->end()
            ->arrayNode('routes')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('login')->defaultValue('wlindabla_user_login')->end()
                    ->scalarNode('login_path')->defaultValue('/admin/form/login')->end()
                    ->scalarNode('logout')->defaultValue('wlindabla_user_logout')->end()
                    ->scalarNode('logout_path')->defaultValue("/admin/logout")->end()
                    ->scalarNode('home')->defaultNull()->end()
                ->end()
            ->end()
            ->arrayNode('templates')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('login')->defaultValue('@WlindablaUser/security/login.html.twig')->end()
                    ->scalarNode('access_denied')->defaultValue('@WlindablaUser/security/error403.html.twig')->end()
                    ->scalarNode('outcome_page')->defaultValue('@WlindablaUser/email/security/checkEmail.html.twig')->end()
                    ->scalarNode('email_verification')->defaultValue('@WlindablaUser/email/profile/admin_account_confirmation.html.twig')->end()
                    ->scalarNode('email_verification_result')->defaultValue('@WlindablaUser/email/security/checkEmail.html.twig')->end()
                    ->scalarNode('toggle_user_account')->defaultValue('@WlindablaUser/email/profile/toggle_user_account.html.twig')->end()
                    ->scalarNode('temporary_password')->defaultValue('@WlindablaUser/email/profile/temporary_password.html.twig')->end()
                    ->scalarNode('password_reset_request')->defaultValue('@WlindablaUser/security/passwordResetRequest.html.twig')->end()
                    ->scalarNode('password_reset_email')->defaultValue('@WlindablaUser/email/security/passwordResetRequest.html.twig')->end()
                    ->scalarNode('two_factor_show')->defaultValue('@WlindablaUser/security/twoFactorAuth.html.twig')->end()
                    ->scalarNode('two_factor_code_email')->defaultValue('@WlindablaUser/email/security/twoFactorCode.html.twig')->end()
                    ->scalarNode('email_change_old_notification')->defaultValue('@WlindablaUser/email/security/emailChangeOldNotification.html.twig')->end()
                    ->scalarNode('email_change_new_confirmation')->defaultValue('@WlindablaUser/email/security/emailChangeNewConfirmation.html.twig')->end()
                    ->scalarNode('email_change_applied')->defaultValue('@WlindablaUser/email/security/emailChangeApplied.html.twig')->end()
                    ->scalarNode('email_change_not_me_alert')->defaultValue('@WlindablaUser/email/security/emailChangeNotMeAlert.html.twig')->end()
                    ->scalarNode('email_change_validation_pending')->defaultValue('@WlindablaUser/email/security/emailChangeValidationPending.html.twig')->end()
                    ->scalarNode('email_change_validation_outcome')->defaultValue('@WlindablaUser/email/security/emailChangeValidationOutcome.html.twig')->end()
                ->end()
            ->end()
            ->arrayNode('password_reset')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('request_path')->defaultValue('/forget-password')->end()
                    ->scalarNode('confirm_path')->defaultValue('/reset-password/confirm/{token}/{id}')->end()
                ->end()
            ->end()
            ->arrayNode('session_control')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('redis_session_prefix')->defaultNull()->end()
                    ->integerNode('inactivity_hours')->defaultValue(4)->end()
                    ->integerNode('max_duration_hours')->defaultValue(24)->end()
                    ->scalarNode('firewall_name')->defaultNull()->end()
                    ->arrayNode('excluded_routes')->scalarPrototype()->end()->defaultValue([])->end()
                ->end()
            ->end()
            ->arrayNode('two_factor')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('firewall_name')->defaultNull()->end()
                    ->arrayNode('allowed_routes_while_pending')->scalarPrototype()->end()->defaultValue([])->end()
                    ->integerNode('inactivity_threshold_seconds')->defaultValue(2 * 3600)->end()
                    ->integerNode('absolute_cap_seconds')->defaultValue(24 * 3600)->end()
                    ->integerNode('activity_touch_throttle_seconds')->defaultValue(5 * 60)->end()
                ->end()
            ->end()
            ->arrayNode('email_change')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('token_lifetime')->defaultValue(3600)->end()
                    ->integerNode('retry_cooldown')->defaultValue(86400)->end()
                    ->arrayNode('exempt_roles_on_self')->scalarPrototype()->end()->defaultValue(['ROLE_SUPER_ADMIN'])->end()
                    ->scalarNode('recovery_initiator_role')->defaultValue('ROLE_SUPER_ADMIN')->end()
                ->end()
            ->end()
        ->end();
    }

    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $parameters = $container->parameters();

        foreach ($config['classes'] as $key => $value) {
            $parameters->set('wlindabla_user.classes.'.$key, $value);
        }

        $parameters
            ->set('wlindabla_user.default_role', $config['default_role'])
            ->set('wlindabla_user.security.user_cache_ttl', $config['security']['user_cache_ttl'])
            ->set('wlindabla_user.templates', $config['templates'])
            ->set('wlindabla_user.routes.login', $config['routes']['login'])
            ->set('wlindabla_user.routes.login_path', $config['routes']['login_path'])
            ->set('wlindabla_user.routes.logout', $config['routes']['logout'])
            ->set('wlindabla_user.routes.logout_path', $config['routes']['logout_path'])
            ->set('wlindabla_user.routes.home', $config['routes']['home'])
            ->set('wlindabla_user.password_reset.request_path', $config['password_reset']['request_path'])
            ->set('wlindabla_user.password_reset.confirm_path', $config['password_reset']['confirm_path'])
            ->set('wlindabla_user.session_control.redis_session_prefix', $config['session_control']['redis_session_prefix'])
            ->set('wlindabla_user.session_control.inactivity_hours', $config['session_control']['inactivity_hours'])
            ->set('wlindabla_user.session_control.max_duration_hours', $config['session_control']['max_duration_hours'])
            ->set('wlindabla_user.session_control.firewall_name', $config['session_control']['firewall_name'])
            ->set('wlindabla_user.session_control.excluded_routes', $config['session_control']['excluded_routes'])
            ->set('wlindabla_user.two_factor.firewall_name', $config['two_factor']['firewall_name'])
            ->set('wlindabla_user.two_factor.allowed_routes_while_pending', $config['two_factor']['allowed_routes_while_pending'])
            ->set('wlindabla_user.two_factor.inactivity_threshold_seconds', $config['two_factor']['inactivity_threshold_seconds'])
            ->set('wlindabla_user.two_factor.absolute_cap_seconds', $config['two_factor']['absolute_cap_seconds'])
            ->set('wlindabla_user.two_factor.activity_touch_throttle_seconds', $config['two_factor']['activity_touch_throttle_seconds'])
            ->set('wlindabla_user.email_change.token_lifetime', $config['email_change']['token_lifetime'])
            ->set('wlindabla_user.email_change.retry_cooldown', $config['email_change']['retry_cooldown'])
            ->set('wlindabla_user.email_change.exempt_roles_on_self', $config['email_change']['exempt_roles_on_self'])
            ->set('wlindabla_user.email_change.recovery_initiator_role', $config['email_change']['recovery_initiator_role']);

        // Core: always loaded — the User module is the only non-optional one.
        $container->import('../config/services.php');

        $classes = $config['classes'];

        // Optional modules whose controllers must be routed (see WlindablaRouteLoader).
        $routingModules = [WlindablaRouteLoader::MODULE_CORE];

        if (null !== $classes['user_session']) {
            if (null === $config['session_control']['redis_session_prefix']) {
                throw new \LogicException('wlindabla_user.classes.user_session is set, so wlindabla_user.session_control.redis_session_prefix is required.');
            }

            if (null === $config['session_control']['firewall_name']) {
                throw new \LogicException('wlindabla_user.classes.user_session is set, so wlindabla_user.session_control.firewall_name is required.');
            }

            $container->import('../config/services_user_session.php');
            $routingModules[] = WlindablaRouteLoader::MODULE_USER_SESSION;
        }

        if (null !== $classes['logger_login_user']) {
            $container->import('../config/services_logger_login_user.php');
        }

        if (null !== $classes['two_factor_code']) {
            if (null === $config['two_factor']['firewall_name']) {
                throw new \LogicException('wlindabla_user.classes.two_factor_code is set, so wlindabla_user.two_factor.firewall_name is required.');
            }

            $container->import('../config/services_two_factor.php');
            $routingModules[] = WlindablaRouteLoader::MODULE_TWO_FACTOR;
        }

        $permissionClasses = [$classes['permission_role'], $classes['permission_group'], $classes['user_permission_role'], $classes['user_permission_group']];

        if (\in_array(null, $permissionClasses, true) && \array_filter($permissionClasses)) {
            throw new \LogicException('The Permission module requires all four of classes.permission_role, permission_group, user_permission_role and user_permission_group to be set together.');
        }

        if (null !== $classes['permission_role']) {
            $container->import('../config/services_permission.php');
        }

        $emailChangeClasses = [$classes['email_change_request'], $classes['email_change_validation']];

        if (\in_array(null, $emailChangeClasses, true) && \array_filter($emailChangeClasses)) {
            throw new \LogicException('The EmailChange module requires both classes.email_change_request and email_change_validation to be set together.');
        }

        if (null !== $classes['email_change_request']) {
            $container->import('../config/services_email_change.php');
            $routingModules[] = WlindablaRouteLoader::MODULE_EMAIL_CHANGE;
        }

        $parameters->set(WlindablaRouteLoader::ENABLED_MODULES_PARAMETER, $routingModules);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $builder->getExtensionConfig('wlindabla_user')[0] ?? [];
        $classes = $config['classes'] ?? [];
        $userClass = $classes['user'] ?? null;

        if (null === $userClass) {
            return;
        }

        $resolveTargetEntities = [UserInterface::class => $userClass];

        $optionalInterfaceMap = [
            'permission_role' => PermissionRoleInterface::class,
            'permission_group' => PermissionGroupInterface::class,
            'user_permission_role' => UserPermissionRoleInterface::class,
            'user_permission_group' => UserPermissionGroupInterface::class,
            'email_change_request' => EmailChangeRequestInterface::class,
            'email_change_validation' => EmailChangeValidationInterface::class,
        ];

        foreach ($optionalInterfaceMap as $classKey => $interface) {
            if (isset($classes[$classKey])) {
                $resolveTargetEntities[$interface] = $classes[$classKey];
            }
        }

        $container->extension('doctrine', [
            'orm' => [
                'resolve_target_entities' => $resolveTargetEntities,
            ],
        ]);

        // Self-registers every FQCN this bundle dispatches asynchronously via
        // AsyncMethodDispatcherInterface, so a consuming project never has to
        // maintain (and risk letting drift out of sync) this whitelist by
        // hand. wlindabla_share_service.async_method_dispatcher.allowed_services
        // is a scalarPrototype array: Symfony merges it across every source
        // that sets it for the same extension, so the project's own
        // allowed_services entries (its own CommandHandlers, etc.) are kept
        // alongside this list, not replaced by it.
        if ($builder->hasExtension('wlindabla_share_service')) {
            $container->extension('wlindabla_share_service', [
                'async_method_dispatcher' => [
                    'allowed_services' => $this->asyncDispatchedServices(),
                ],
            ]);
        }
    }

    /**
     * Every FQCN this bundle passes to AsyncMethodDispatcherInterface::dispatch()
     * anywhere in its own code — kept here as the single source of truth.
     *
     * @return list<string>
     */
    private function asyncDispatchedServices(): array
    {
        return [
            \Psr\Log\LoggerInterface::class,
            \Wlindabla\UserBundle\Domain\User\Application\CommandHandler\UpdateUserProfileHandler::class,
            \Wlindabla\UserBundle\Domain\User\Application\CommandHandler\GenerateTemporaryPasswordHandler::class,
            \Wlindabla\UserBundle\Domain\User\Application\CommandHandler\ToggleUserAccountHandler::class,
            \Wlindabla\UserBundle\Domain\User\Application\Manager\UserManagerInterface::class,
            \Wlindabla\UserBundle\Domain\User\Service\UpdatePasswordUserServiceInterface::class,
            \Wlindabla\UserBundle\Infrastructure\Service\PasswordResetMailerService::class,
            \Wlindabla\UserBundle\Domain\UserSession\Application\Manager\UserSessionManagerInterface::class,
            \Wlindabla\UserBundle\Domain\LoggerLoginUser\Application\Manager\LoggerLoginUserManagerInterface::class,
            \Wlindabla\UserBundle\Domain\TwoFactorAuth\Application\Manager\TwoFactorCodeManagerInterface::class,
            \Wlindabla\UserBundle\Infrastructure\Service\EmailChangeRequestMailerService::class
        ];
    }
}