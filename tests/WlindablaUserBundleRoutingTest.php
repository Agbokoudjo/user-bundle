<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wlindabla\UserBundle\Infrastructure\Routing\WlindablaRouteLoader;
use Wlindabla\UserBundle\WlindablaUserBundle;

/**
 * The bundle must tell the route loader which optional modules are enabled,
 * and register the loader as a "routing.loader".
 */
final class WlindablaUserBundleRoutingTest extends TestCase
{
    public function testOnlyCoreRoutesByDefault(): void
    {
        $container = $this->loadBundle(['classes' => ['user' => 'App\Entity\User']]);

        $this->assertSame([WlindablaRouteLoader::MODULE_CORE], $container->getParameter(WlindablaRouteLoader::ENABLED_MODULES_PARAMETER));
    }

    public function testOptionalModulesFollowTheConfiguredClasses(): void
    {
        $container = $this->loadBundle([
            'classes' => [
                'user' => 'App\Entity\User',
                'user_session' => 'App\Entity\UserSession',
                'two_factor_code' => 'App\Entity\TwoFactorCode',
                'email_change_request' => 'App\Entity\EmailChangeRequest',
                'email_change_validation' => 'App\Entity\EmailChangeValidation',
            ],
            'session_control' => ['redis_session_prefix' => 'sess_', 'firewall_name' => 'main'],
            'two_factor' => ['firewall_name' => 'main'],
        ]);

        $this->assertSame([
            WlindablaRouteLoader::MODULE_CORE,
            WlindablaRouteLoader::MODULE_USER_SESSION,
            WlindablaRouteLoader::MODULE_TWO_FACTOR,
            WlindablaRouteLoader::MODULE_EMAIL_CHANGE,
        ], $container->getParameter(WlindablaRouteLoader::ENABLED_MODULES_PARAMETER));
    }

    public function testRouteLoaderIsRegisteredAsRoutingLoader(): void
    {
        $container = $this->loadBundle(['classes' => ['user' => 'App\Entity\User']]);

        $this->assertTrue($container->hasDefinition(WlindablaRouteLoader::class));
        $this->assertTrue($container->getDefinition(WlindablaRouteLoader::class)->hasTag('routing.loader'));
    }

    /** @param array<string, mixed> $config */
    private function loadBundle(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        (new WlindablaUserBundle())->getContainerExtension()->load([$config], $container);

        return $container;
    }
}
