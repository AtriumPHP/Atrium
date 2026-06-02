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

    $routes->add('atrium_resource', '/admin/{resource}')
        ->controller([AdminController::class, 'resource'])
        ->requirements(['resource' => '[a-z0-9-]+']);
};
