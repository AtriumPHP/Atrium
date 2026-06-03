<?php

declare(strict_types=1);

use Atrium\Controller\AdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Parametric panel routes (PNL-01, LAY-05). A small fixed set covers every
 * resource; adding a CRUD resource needs no route registration.
 *
 * Every path is mounted under the `%atrium.path_prefix%` container parameter
 * (default `/admin`, set from the `atrium.path_prefix` config key). Symfony
 * resolves the placeholder when the router compiles, so a single setting moves
 * both where the panel *matches* requests and the URLs it *generates* — they
 * can never drift apart. Keep the value without a trailing slash, matching the
 * `/admin` default. To serve the panel on its own host (e.g.
 * `admin.example.com`) add a `host:` to the import instead; see the
 * integration guide.
 */
return static function (RoutingConfigurator $routes): void {
    $prefix = '%atrium.path_prefix%';

    $routes->add('atrium_dashboard', $prefix)
        ->controller([AdminController::class, 'dashboard']);

    $routes->add('atrium_resource_create', $prefix.'/{resource}/new')
        ->controller([AdminController::class, 'create'])
        ->requirements(['resource' => '[a-z0-9-]+']);

    $routes->add('atrium_resource_edit', $prefix.'/{resource}/{id}/edit')
        ->controller([AdminController::class, 'edit'])
        ->requirements(['resource' => '[a-z0-9-]+', 'id' => '[^/]+']);

    // Bare record URL: the read-only View screen (VIEW-01). Declared after
    // `/new` so a literal `new` still routes to create (first match wins).
    $routes->add('atrium_resource_view', $prefix.'/{resource}/{id}')
        ->controller([AdminController::class, 'view'])
        ->requirements(['resource' => '[a-z0-9-]+', 'id' => '[^/]+']);

    // Single-segment catch-all: a dashboard or a resource list (dashboards win).
    $routes->add('atrium_page', $prefix.'/{slug}')
        ->controller([AdminController::class, 'page'])
        ->requirements(['slug' => '[a-z0-9-]+']);
};
