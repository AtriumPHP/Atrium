<?php

declare(strict_types=1);

namespace Atrium\Controller;

use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The panel's HTTP entry point (PNL-01).
 *
 * A small set of parametric routes cover every resource: the dashboard and the
 * per-resource list page. The list page is a thin host template that embeds the
 * reactive DataTable Live Component — the controller itself does no querying.
 */
final readonly class AdminController
{
    public function __construct(
        private Environment $twig,
        private ResourceRegistry $registry,
        private string $brand,
        private string $pathPrefix,
    ) {
    }

    public function dashboard(): Response
    {
        return new Response($this->twig->render('@Atrium/admin/dashboard.html.twig', [
            'panel' => $this->panel(),
        ]));
    }

    public function resource(string $resource): Response
    {
        if (!$this->registry->hasSlug($resource)) {
            throw new NotFoundHttpException(\sprintf('No admin resource registered for "%s".', $resource));
        }

        return new Response($this->twig->render('@Atrium/admin/resource.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $this->registry->getBySlug($resource),
        ]));
    }

    /**
     * @return array{brand: string, pathPrefix: string, resources: list<array{slug: string, label: string, group: string|null, icon: string|null, url: string, active: bool}>}
     */
    private function panel(?string $activeSlug = null): array
    {
        $resources = [];
        foreach ($this->registry->all() as $resource) {
            $resources[] = $this->navItem($resource, $activeSlug);
        }

        return [
            'brand' => $this->brand,
            'pathPrefix' => $this->pathPrefix,
            'resources' => $resources,
        ];
    }

    /**
     * @return array{slug: string, label: string, group: string|null, icon: string|null, url: string, active: bool}
     */
    private function navItem(AdminResource $resource, ?string $activeSlug): array
    {
        $slug = $resource->getSlug();

        return [
            'slug' => $slug,
            'label' => $resource->getLabel(),
            'group' => $resource->getNavigationGroup(),
            'icon' => $resource->getNavigationIcon(),
            'url' => rtrim($this->pathPrefix, '/').'/'.$slug,
            'active' => $slug === $activeSlug,
        ];
    }
}
