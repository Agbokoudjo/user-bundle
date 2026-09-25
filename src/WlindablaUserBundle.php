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
use Wlindabla\UserBundle\Infrastructure\Model\UserInterface;

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
                        ->scalarNode('user')
                            ->info('FQCN of your User entity (extends the bundle base user).')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
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
                        ->integerNode('user_cache_ttl')
                            ->info('Seconds a loaded/refreshed user stays cached by UserProvider. Lower it if disabling an account or changing roles must take effect faster than this delay.')
                            ->defaultValue(43200)
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('email_verification')
                            ->info('Twig template rendered by UserAccountEmailVerificationSubscriber.')
                            ->defaultValue('email/profile/admin_account_confirmation.html.twig')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{classes: array{user: string}, default_role: string} $config
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->parameters()
            ->set('wlindabla_user.classes.user', $config['classes']['user'])
            ->set('wlindabla_user.default_role', $config['default_role'])
            ->set('wlindabla_user.security.user_cache_ttl', $config['security']['user_cache_ttl'])
            ->set('wlindabla_user.templates', $config['templates']);
            ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $builder->getExtensionConfig('wlindabla_user');
        $userClass = $config[0]['classes']['user'] ?? null;

        if (null === $userClass) {
            return;
        }

        $container->extension('doctrine', [
            'orm' => [
                'resolve_target_entities' => [
                    UserInterface::class => $userClass,
                ],
            ],
        ]);
    }
}