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

namespace Wlindabla\UserBundle\Infrastructure\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouteCollection;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\AdminFormLoginController;
use Wlindabla\UserBundle\UI\Http\Controller\Admin\UserLogoutController;
use Wlindabla\UserBundle\UI\Http\Controller\EmailChangeConfirmationController;
use Wlindabla\UserBundle\UI\Http\Controller\EmailVerificationController;
use Wlindabla\UserBundle\UI\Http\Controller\PasswordResetConfirmationController;
use Wlindabla\UserBundle\UI\Http\Controller\PasswordResetRequestController;
use Wlindabla\UserBundle\UI\Http\Controller\SessionConflictController;
use Wlindabla\UserBundle\UI\Http\Controller\TwoFactorAuthController;

/**
 * Route loader of WlindablaUserBundle.
 *
 * Why a custom loader instead of a plain `type: attribute` import?
 *
 *  1. Symfony resolves `%parameters%` in route *paths*, but never in route *names*.
 *     The login/logout routes are named after the bundle configuration
 *     (`wlindabla_user.routes.login`, `wlindabla_user.routes.logout`), so a plain
 *     import would register routes literally called "%wlindabla_user.routes.login%".
 *  2. Optional modules (user session, two-factor, email change) must only expose
 *     their routes when the host application enabled them, otherwise the router
 *     would point to controllers that are not registered as services.
 *
 * The host application imports it through the bundle entry point:
 *
 *     # config/routes/wlindabla_user.yaml
 *     wlindabla_user:
 *         resource: '@WlindablaUserBundle/config/routes.php'
 *         # prefix: /account
 *
 * The loader is stateless: it can be imported several times (e.g. with different
 * prefixes or hosts) without any "loaded twice" guard.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package <https://github.com/Agbokoudjo/>
 */
final class WlindablaRouteLoader extends Loader
{
    /** Resource type used by `config/routes.php`: `$routes->import('.', self::TYPE)`. */
    public const string TYPE = 'wlindabla_user';

    /**
     * Container parameter (list<string>) filled by WlindablaUserBundle::loadExtension()
     * with the optional modules enabled by the host configuration.
     */
    public const string ENABLED_MODULES_PARAMETER = 'wlindabla_user.routing.enabled_modules';

    public const string MODULE_CORE = 'core';
    public const string MODULE_USER_SESSION = 'user_session';
    public const string MODULE_TWO_FACTOR = 'two_factor';
    public const string MODULE_EMAIL_CHANGE = 'email_change';

    /**
     * Controllers carrying #[Route] attributes, grouped by module.
     * The order is the registration order inside the final RouteCollection.
     *
     * @var array<string, list<class-string>>
     */
    private const array MODULE_CONTROLLERS = [
        self::MODULE_CORE => [
            AdminFormLoginController::class,
            UserLogoutController::class,
            EmailVerificationController::class,
            PasswordResetRequestController::class,
            PasswordResetConfirmationController::class,
        ],
        self::MODULE_USER_SESSION => [
            SessionConflictController::class,
        ],
        self::MODULE_TWO_FACTOR => [
            TwoFactorAuthController::class,
        ],
        self::MODULE_EMAIL_CHANGE => [
            EmailChangeConfirmationController::class,
        ],
    ];

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $collection = new RouteCollection();

        /** @var array<string, mixed> $usedParameters parameter name => value, for cache invalidation */
        $usedParameters = [];
        /** @var array<string, string> $takenNames resolved route name => controller that declared it */
        $takenNames = [];

        foreach ($this->enabledModules($usedParameters) as $module) {
            foreach (self::MODULE_CONTROLLERS[$module] as $controller) {
                $imported = $this->import($controller, 'attribute');

                if (!$imported instanceof RouteCollection) {
                    throw new \LogicException(\sprintf('Importing the controller "%s" did not return a RouteCollection. Is the attribute route loader available?', $controller));
                }

                $collection->addCollection($this->withResolvedNames($imported, $controller, $takenNames, $usedParameters));
            }
        }

        // Lets the router cache be rebuilt (debug mode) when a configuration value
        // used in a route name, or the enabled modules, changes.
        if ([] !== $usedParameters) {
            $collection->addResource(new ContainerParametersResource($usedParameters));
        }

        return $collection;
    }

    /**
     * @param array<string, mixed> $usedParameters
     *
     * @return list<string> enabled module identifiers, "core" first, in canonical order
     */
    private function enabledModules(array &$usedParameters): array
    {
        $requested = [];

        if ($this->parameterBag->has(self::ENABLED_MODULES_PARAMETER)) {
            $requested = $this->parameterBag->get(self::ENABLED_MODULES_PARAMETER);
            $usedParameters[self::ENABLED_MODULES_PARAMETER] = $requested;

            if (!\is_array($requested)) {
                throw new \LogicException(\sprintf('The container parameter "%s" must be an array of module names, "%s" given.', self::ENABLED_MODULES_PARAMETER, \get_debug_type($requested)));
            }
        }

        foreach ($requested as $module) {
            if (!\is_string($module) || !isset(self::MODULE_CONTROLLERS[$module])) {
                throw new \LogicException(\sprintf('Unknown WlindablaUserBundle routing module "%s". Expected one of: "%s".', \is_scalar($module) ? (string) $module : \get_debug_type($module), \implode('", "', \array_keys(self::MODULE_CONTROLLERS))));
            }
        }

        // The core module is mandatory; the others follow the canonical order.
        $enabled = [self::MODULE_CORE];

        foreach (\array_keys(self::MODULE_CONTROLLERS) as $module) {
            if (self::MODULE_CORE !== $module && \in_array($module, $requested, true)) {
                $enabled[] = $module;
            }
        }

        return $enabled;
    }

    /**
     * Returns a copy of $imported whose route names (and alias names) have their
     * "%parameters%" resolved. Resources, such as the controller file, are kept
     * so the cache stays tracked.
     *
     * @param array<string, string> $takenNames
     * @param array<string, mixed>  $usedParameters
     */
    private function withResolvedNames(
        RouteCollection $imported,
        string $controller,
        array &$takenNames,
        array &$usedParameters,
    ): RouteCollection {
        $renamed = new RouteCollection();

        foreach ($imported->all() as $name => $route) {
            $resolved = $this->resolveName((string) $name, $controller, $takenNames, $usedParameters);

            // Symfony's Router also resolves these at runtime; doing it here makes the
            // loaded collection self-contained, whichever loader stack imports it.
            $route->setPath($this->resolveParameters($route->getPath(), \sprintf('the path of route "%s" declared by "%s"', $name, $controller), $usedParameters));

            if ('' !== $route->getHost()) {
                $route->setHost($this->resolveParameters($route->getHost(), \sprintf('the host of route "%s" declared by "%s"', $name, $controller), $usedParameters));
            }

            $renamed->add($resolved, $route);
        }

        foreach ($imported->getAliases() as $aliasName => $alias) {
            $resolvedAlias = $this->resolveName((string) $aliasName, $controller, $takenNames, $usedParameters);
            $target = $this->resolveName($alias->getId(), $controller, $takenNames, $usedParameters, false);
            $renamed->addAlias($resolvedAlias, $target);
        }

        foreach ($imported->getResources() as $resource) {
            $renamed->addResource($resource);
        }

        return $renamed;
    }

    /**
     * @param array<string, string> $takenNames
     * @param array<string, mixed>  $usedParameters
     */
    private function resolveName(
        string $name,
        string $controller,
        array &$takenNames,
        array &$usedParameters,
        bool $register = true,
    ): string {
        $resolved = $this->resolveParameters($name, \sprintf('the route name "%s" declared by "%s"', $name, $controller), $usedParameters);

        if ('' === $resolved) {
            throw new \LogicException(\sprintf('The route name "%s" declared by "%s" could not be resolved to a non-empty string. Check the "wlindabla_user.routes" configuration.', $name, $controller));
        }

        if (!$register) {
            return $resolved;
        }

        if (isset($takenNames[$resolved])) {
            throw new \LogicException(\sprintf('The route name "%s" (declared as "%s" by "%s") is already used by "%s". Route names of WlindablaUserBundle must be unique: check the "wlindabla_user.routes" configuration.', $resolved, $name, $controller, $takenNames[$resolved]));
        }

        $takenNames[$resolved] = $controller;

        return $resolved;
    }

    /**
     * Replaces "%parameter%" placeholders exactly like the framework Router does
     * for route paths ("%%" is an escaped "%", "%env()%" is forbidden, values must
     * be scalar), and records every parameter used so the router cache can be
     * invalidated when it changes.
     *
     * @param array<string, mixed> $usedParameters
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException when a parameter does not exist
     * @throws \LogicException                                                                when a parameter value is not scalar
     */
    private function resolveParameters(string $value, string $subject, array &$usedParameters): string
    {
        if (!\str_contains($value, '%')) {
            return $value;
        }

        $resolved = \preg_replace_callback(
            '/%%|%([^%\s]++)%/',
            function (array $match) use ($subject, &$usedParameters): string {
                if (!isset($match[1])) {
                    return '%%'; // escaped percent sign, unescaped once at the end
                }

                if (\preg_match('/^env\((?:\w++:)*+\w++\)$/', $match[1])) {
                    throw new \LogicException(\sprintf('Using "%%%s%%" is not allowed in %s: environment variables cannot be resolved at routing time.', $match[1], $subject));
                }

                $parameter = $this->parameterBag->get($match[1]);

                if (!\is_scalar($parameter)) {
                    throw new \LogicException(\sprintf('%s could not be resolved: the container parameter "%s" must be a string or numeric, "%s" given.', \ucfirst($subject), $match[1], \get_debug_type($parameter)));
                }

                $usedParameters[$match[1]] = $parameter;

                if (\is_string($parameter)) {
                    $parameter = $this->resolveParameters($parameter, $subject, $usedParameters);
                }

                return false === $parameter ? '0' : (string) $parameter;
            },
            $value,
        );

        return \str_replace('%%', '%', $resolved);
    }
}