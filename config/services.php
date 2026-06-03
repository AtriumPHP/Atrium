<?php

declare(strict_types=1);

use Atrium\Controller\AdminController;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\Resource\ResourceRegistry;
use Atrium\Twig\PanelAssetsExtension;
use Atrium\Widget\WidgetRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/*
 * Core service wiring for the Atrium bundle.
 *
 * Resources are collected via the `atrium.resource` tag (autoconfigured in
 * AtriumBundle). The Doctrine data provider is registered separately and only
 * when DoctrineBundle is present (see config/doctrine.php).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ResourceRegistry::class)
        ->args([tagged_iterator('atrium.resource')])
        ->public();

    $services->set(WidgetRegistry::class)
        ->args([tagged_iterator('atrium.widget')])
        ->public();

    $services->set(AdminController::class)
        ->args([
            service('twig'),
            service(ResourceRegistry::class),
            param('atrium.brand'),
            param('atrium.path_prefix'),
            service(DataProviderInterface::class)->ignoreOnInvalid(),
        ])
        ->tag('controller.service_arguments');

    $services->load('Atrium\\Twig\\Components\\', \dirname(__DIR__).'/src/Twig/Components/')
        ->autowire()
        ->autoconfigure();

    $services->set(PanelAssetsExtension::class)
        ->args([
            service('asset_mapper.importmap.renderer')->ignoreOnInvalid(),
            service('assets.packages')->ignoreOnInvalid(),
        ])
        ->tag('twig.extension');
};
