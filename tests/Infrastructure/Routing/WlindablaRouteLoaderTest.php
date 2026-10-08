<?php

declare(strict_types=1);

namespace Wlindabla\UserBundle\Tests\Infrastructure\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Wlindabla\UserBundle\Infrastructure\Routing\WlindablaRouteLoader;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\AdminFormLoginController;

final class WlindablaRouteLoaderTest extends TestCase
{
    private const array DEFAULT_PARAMETERS = [
        'wlindabla_user.routes.login' => 'wlindabla_user_login',
        'wlindabla_user.routes.login_path' => '/admin/form/login',
        'wlindabla_user.routes.logout' => 'wlindabla_user_logout',
        'wlindabla_user.routes.logout_path' => '/admin/logout',
        'wlindabla_user.password_reset.request_path' => '/forget-password',
        'wlindabla_user.password_reset.confirm_path' => '/reset-password/confirm/{token}/{id}',
        WlindablaRouteLoader::ENABLED_MODULES_PARAMETER => [WlindablaRouteLoader::MODULE_CORE],
    ];

    public function testPlainAttributeImportLeavesParametersInRouteNames(): void
    {
        // Documents the problem the custom loader solves.
        $routes = $this->router(self::DEFAULT_PARAMETERS, false)->load(AdminFormLoginController::class, 'attribute');

        $this->assertTrue(null !== $routes->get('%wlindabla_user.routes.login%'));
        $this->assertTrue(null === $routes->get('wlindabla_user_login'));
    }

    public function testCoreRoutesAreLoadedWithResolvedNames(): void
    {
        $routes = $this->load(self::DEFAULT_PARAMETERS);

        foreach ([
            'wlindabla_user_login',
            'wlindabla_user_logout',
            'wlindabla_user.verify.email',
            'wlindabla_user_password_reset_request',
            'wlindabla_user_password_reset_confirm',
        ] as $name) {
            $this->assertTrue(null !== $routes->get($name), \sprintf('Missing route "%s".', $name));
        }

        foreach (\array_keys($routes->all()) as $name) {
            $this->assertFalse(\str_contains((string) $name, '%'), \sprintf('Unresolved route name "%s".', $name));
        }

        $this->assertSame('/admin/form/login', $routes->get('wlindabla_user_login')->getPath());
        $this->assertSame(['GET', 'POST'], $routes->get('wlindabla_user_login')->getMethods());
        // Symfony sets the bare FQCN as _controller for invokable controllers.
        $this->assertSame(AdminFormLoginController::class, $routes->get('wlindabla_user_login')->getDefault('_controller'));
    }

    public function testControllerAliasesPointToResolvedRouteNames(): void
    {
        $routes = $this->load([...self::DEFAULT_PARAMETERS, 'wlindabla_user.routes.login' => 'app_login']);

        $aliases = $routes->getAliases();
        $this->assertTrue(isset($aliases[AdminFormLoginController::class]), 'FQCN alias lost.');
        $this->assertSame('app_login', $aliases[AdminFormLoginController::class]->getId());
        $this->assertSame('app_login', $aliases[AdminFormLoginController::class.'::__invoke']->getId());

        foreach ($aliases as $aliasName => $alias) {
            $this->assertFalse(\str_contains((string) $aliasName, '%'), \sprintf('Unresolved alias name "%s".', $aliasName));
            $this->assertTrue(null !== $routes->get($alias->getId()), \sprintf('Alias "%s" targets the missing route "%s".', $aliasName, $alias->getId()));
        }
    }

    public function testParametersInPathsAreResolved(): void
    {
        $routes = $this->load(self::DEFAULT_PARAMETERS);

        $this->assertSame('/forget-password', $routes->get('wlindabla_user_password_reset_request')->getPath());
        $this->assertSame('/reset-password/confirm/{token}/{id}', $routes->get('wlindabla_user_password_reset_confirm')->getPath());
        $this->assertSame('/verify-email/{token}/{id}', $routes->get('wlindabla_user.verify.email')->getPath());
    }

    public function testEnvParametersAreRejectedInRouting(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not allowed');

        $this->load([...self::DEFAULT_PARAMETERS, 'wlindabla_user.routes.login_path' => '/%env(APP_LOGIN)%']);
    }

    public function testLoaderCanBeImportedSeveralTimes(): void
    {
        $router = $this->router(self::DEFAULT_PARAMETERS);

        $first = $router->load('.', WlindablaRouteLoader::TYPE);
        $second = $router->load('.', WlindablaRouteLoader::TYPE);

        $this->assertSame(\array_keys($first->all()), \array_keys($second->all()));
    }

    public function testOptionalModulesAreNotRoutedWhenDisabled(): void
    {
        $routes = $this->load(self::DEFAULT_PARAMETERS);

        $this->assertTrue(null === $routes->get('wlindabla_user_two_factor_show'));
        $this->assertTrue(null === $routes->get('wlindabla_user_session_conflict'));
        $this->assertTrue(null === $routes->get('wlindabla_user_email_change_confirm_old'));
    }

    public function testOptionalModulesAreRoutedWhenEnabled(): void
    {
        $routes = $this->load([
            ...self::DEFAULT_PARAMETERS,
            WlindablaRouteLoader::ENABLED_MODULES_PARAMETER => [
                WlindablaRouteLoader::MODULE_CORE,
                WlindablaRouteLoader::MODULE_USER_SESSION,
                WlindablaRouteLoader::MODULE_TWO_FACTOR,
                WlindablaRouteLoader::MODULE_EMAIL_CHANGE,
            ],
        ]);

        foreach ([
            'wlindabla_user_two_factor_show',
            'wlindabla_user_two_factor_verify',
            'wlindabla_user_two_factor_resend',
            'wlindabla_user_session_conflict',
            'wlindabla_user_email_change_confirm_old',
            'wlindabla_user_email_change_confirm_new',
            'wlindabla_user_email_change_not_me',
        ] as $name) {
            $this->assertTrue(null !== $routes->get($name), \sprintf('Missing route "%s".', $name));
        }
    }

    public function testCustomConfiguredNamesAreHonoured(): void
    {
        $routes = $this->load([
            ...self::DEFAULT_PARAMETERS,
            'wlindabla_user.routes.login' => 'app_login',
            'wlindabla_user.routes.login_path' => '/connexion',
            'wlindabla_user.routes.logout' => 'app_logout',
        ]);

        $this->assertSame('/connexion', $routes->get('app_login')->getPath());
        $this->assertTrue(null !== $routes->get('app_logout'));
        $this->assertTrue(null === $routes->get('wlindabla_user_login'));
    }

    public function testParametersUsedInNamesAreTrackedForCacheInvalidation(): void
    {
        $resources = \array_filter(
            $this->load(self::DEFAULT_PARAMETERS)->getResources(),
            static fn ($r) => $r instanceof ContainerParametersResource,
        );

        $this->assertCount(1, $resources);

        // ContainerParametersResource::__toString() is a hash: inspect the tracked parameters instead.
        $tracked = \reset($resources)->getParameters();
        $this->assertSame('wlindabla_user_login', $tracked['wlindabla_user.routes.login'] ?? null);
        $this->assertSame('/admin/form/login', $tracked['wlindabla_user.routes.login_path'] ?? null);
        $this->assertTrue(\array_key_exists(WlindablaRouteLoader::ENABLED_MODULES_PARAMETER, $tracked));
    }

    public function testDuplicatedRouteNamesAreRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already used');

        $this->load([...self::DEFAULT_PARAMETERS, 'wlindabla_user.routes.logout' => 'wlindabla_user_login']);
    }

    public function testNonStringRouteNameIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('could not be resolved');

        $this->load([...self::DEFAULT_PARAMETERS, 'wlindabla_user.routes.login' => null]);
    }

    public function testMissingParameterIsReported(): void
    {
        $parameters = self::DEFAULT_PARAMETERS;
        unset($parameters['wlindabla_user.routes.login']);

        $this->expectException(ParameterNotFoundException::class);

        $this->load($parameters);
    }

    public function testUnknownModuleIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown WlindablaUserBundle routing module');

        $this->load([...self::DEFAULT_PARAMETERS, WlindablaRouteLoader::ENABLED_MODULES_PARAMETER => ['nope']]);
    }

    public function testCoreRoutesStayAvailableWithoutModulesParameter(): void
    {
        $parameters = self::DEFAULT_PARAMETERS;
        unset($parameters[WlindablaRouteLoader::ENABLED_MODULES_PARAMETER]);

        $this->assertTrue(null !== $this->load($parameters)->get('wlindabla_user_login'));
    }

    public function testBundleRoutesFileImportsTheLoader(): void
    {
        $delegating = $this->router(self::DEFAULT_PARAMETERS, true, $configDir = \dirname(__DIR__, 3).'/config');
        $routes = $delegating->load($configDir.'/routes.php');

        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertTrue(null !== $routes->get('wlindabla_user_login'));
    }

    public function testPrefixIsAppliedByTheHostApplication(): void
    {
        $routes = new RouteCollection();
        $routes->addCollection($this->load(self::DEFAULT_PARAMETERS));
        $routes->addPrefix('/account');

        $this->assertSame('/account/admin/form/login', $routes->get('wlindabla_user_login')->getPath());
    }

    /** @param array<string, mixed> $parameters */
    private function load(array $parameters): RouteCollection
    {
        $routes = $this->router($parameters)->load('.', WlindablaRouteLoader::TYPE);
        $this->assertInstanceOf(RouteCollection::class, $routes);

        return $routes;
    }

    /** @param array<string, mixed> $parameters */
    private function router(array $parameters, bool $withBundleLoader = true, ?string $configDir = null): DelegatingLoader
    {
        $loaders = [new AttributeRouteControllerLoader('test')];

        if ($withBundleLoader) {
            $loaders[] = new WlindablaRouteLoader(new ParameterBag($parameters), 'test');
        }

        if (null !== $configDir) {
            $loaders[] = new PhpFileLoader(new FileLocator($configDir), 'test');
        }

        return new DelegatingLoader(new LoaderResolver($loaders));
    }
}
