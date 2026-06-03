<?php

declare(strict_types=1);

namespace Atrium;

use Atrium\Dashboard\Dashboard;
use Atrium\Resource\AdminResource;
use Atrium\Widget\Widget;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The Atrium admin panel bundle.
 *
 * Phase 0 boilerplate: registers the resource registry and wires
 * autoconfiguration so that any {@see AdminResource} subclass is discovered
 * automatically. UI, data and builder layers are added in later phases.
 */
final class AtriumBundle extends AbstractBundle
{
    protected string $extensionAlias = 'atrium';

    /**
     * Keep config/ and templates/ at the package root rather than under src/.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('path_prefix')
                    ->info('URL prefix the admin panel is mounted under.')
                    ->defaultValue('/admin')
                ->end()
                ->scalarNode('brand')
                    ->info('Brand label shown in the panel shell.')
                    ->defaultValue('Atrium')
                ->end()
            ->end()
        ;
    }

    /**
     * Prepend configuration onto bundles the panel builds on:
     *
     *  - AssetMapper: expose the bundle's precompiled stylesheet through the
     *    `atrium` path so the panel is styled out of the box, with no Tailwind
     *    setup in the consuming app. Apps not using AssetMapper can override the
     *    layout's `stylesheets` block instead.
     *  - UX Icons: register the bundle's shipped Lucide set under the `atrium:`
     *    prefix, so `ux_icon('atrium:cube')` (and the `atrium_icon()` helper)
     *    resolve the panel's own glyphs from disk — no network, no consumer
     *    setup. The host app's bare `assets/icons` namespace is left untouched.
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->extension('ux_icons', [
            'icon_sets' => [
                'atrium' => [
                    'path' => \dirname(__DIR__).'/assets/icons',
                    // The shipped glyphs are stroke-based (Lucide); pin fill to
                    // none so they render correctly even when the host app's
                    // `default_icon_attributes` set `fill: currentColor` (the
                    // value the ux-icons recipe writes by default).
                    'icon_attributes' => ['fill' => 'none'],
                ],
            ],
        ]);

        if (!class_exists(AssetMapper::class)) {
            return;
        }

        $container->extension('framework', [
            'asset_mapper' => [
                'paths' => [\dirname(__DIR__).'/assets/dist' => 'atrium'],
            ],
        ]);
    }

    /**
     * @param array{path_prefix: string, brand: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('atrium.path_prefix', $config['path_prefix']);
        $builder->setParameter('atrium.brand', $config['brand']);

        $builder->registerForAutoconfiguration(AdminResource::class)
            ->addTag('atrium.resource')
        ;

        $builder->registerForAutoconfiguration(Widget::class)
            ->addTag('atrium.widget')
        ;

        $builder->registerForAutoconfiguration(Dashboard::class)
            ->addTag('atrium.dashboard')
        ;

        $container->import(\dirname(__DIR__).'/config/services.php');

        if ($this->hasDoctrineBundle($builder)) {
            $container->import(\dirname(__DIR__).'/config/doctrine.php');
        }
    }

    private function hasDoctrineBundle(ContainerBuilder $builder): bool
    {
        $bundles = $builder->getParameter('kernel.bundles');

        return \is_array($bundles) && isset($bundles['DoctrineBundle']);
    }
}
