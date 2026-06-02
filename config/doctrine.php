<?php

declare(strict_types=1);

use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DoctrineDataProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Doctrine adapter wiring. Imported by AtriumBundle only when DoctrineBundle is
 * registered, so the core stays installable without Doctrine. Selecting a
 * different backend is a single alias override (DAT-02).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(DoctrineDataProvider::class)
        ->autowire();

    $services->alias(DataProviderInterface::class, DoctrineDataProvider::class);
};
