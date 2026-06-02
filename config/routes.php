<?php

declare(strict_types=1);

use Atrium\Controller\AdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Parametric panel routes (PNL-01, LAY-05). A small fixed set covers every
 * resource; adding a CRUD resource needs no route registration.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('atrium_dashboard', '/admin')
        ->controller([AdminController::class, 'dashboard']);

    $routes->add('atrium_resource_create', '/admin/{resource}/new')
        ->controller([AdminController::class, 'create'])
        ->requirements(['resource' => '[a-z0-9-]+']);

    $routes->add('atrium_resource_edit', '/admin/{resource}/{id}/edit')
        ->controller([AdminController::class, 'edit'])
        ->requirements(['resource' => '[a-z0-9-]+', 'id' => '[^/]+']);

    $routes->add('atrium_resource', '/admin/{resource}')
        ->controller([AdminController::class, 'resource'])
        ->requirements(['resource' => '[a-z0-9-]+']);
};
