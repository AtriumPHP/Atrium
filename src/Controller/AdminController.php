<?php

declare(strict_types=1);

namespace Atrium\Controller;

use Atrium\DataProvider\DataProviderInterface;
use Atrium\Page\PageContext;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The panel's HTTP entry point and generic page dispatcher (PNL-01, LAY-05).
 *
 * A small set of parametric routes cover every resource: dashboard, list,
 * create and edit. For create/edit the controller resolves the resource's Page
 * (descriptor) for the action and renders a host template that embeds the
 * reactive Form component — the controller itself does no querying or writing.
 */
final readonly class AdminController
{
    public function __construct(
        private Environment $twig,
        private ResourceRegistry $registry,
        private string $brand,
        private string $pathPrefix,
        private ?DataProviderInterface $dataProvider = null,
    ) {
    }

    public function dashboard(): Response
    {
        return $this->render('@Atrium/admin/dashboard.html.twig', [
            'panel' => $this->panel(),
        ]);
    }

    public function resource(string $resource): Response
    {
        $resourceObject = $this->requireResource($resource);

        return $this->render('@Atrium/admin/resource.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
        ]);
    }

    public function create(string $resource): Response
    {
        $resourceObject = $this->requireResource($resource);
        $page = $resourceObject->resolvePage('create');
        $context = new PageContext($resource, $this->pathPrefix);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
            'heading' => 'New '.$resourceObject->getSingularLabel(),
            'entityId' => null,
            'redirectUrl' => $page?->getRedirectUrl($context),
        ]);
    }

    public function edit(string $resource, string $id): Response
    {
        $resourceObject = $this->requireResource($resource);

        if (null !== $this->dataProvider
            && null === $this->dataProvider->find($resourceObject->getEntityClass(), $id)) {
            throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
        }

        $page = $resourceObject->resolvePage('edit');
        $context = new PageContext($resource, $this->pathPrefix, $id);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
            'heading' => 'Edit '.$resourceObject->getSingularLabel(),
            'entityId' => $id,
            'redirectUrl' => $page?->getRedirectUrl($context),
        ]);
    }

    private function requireResource(string $slug): AdminResource
    {
        if (!$this->registry->hasSlug($slug)) {
            throw new NotFoundHttpException(\sprintf('No admin resource registered for "%s".', $slug));
        }

        return $this->registry->getBySlug($slug);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context));
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
