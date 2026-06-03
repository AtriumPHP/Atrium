<?php

declare(strict_types=1);

namespace Atrium\Controller;

use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardConfiguration;
use Atrium\Dashboard\DashboardRegistry;
use Atrium\Dashboard\DefaultDashboard;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\Page\PageContext;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The panel's HTTP entry point and generic page dispatcher (PNL-01, LAY-05).
 *
 * A small set of parametric routes cover every screen. `/admin` renders the root
 * dashboard; `/admin/{slug}` resolves to a dashboard *or* a resource list (PNL-06)
 * — the two share one slug namespace; `/admin/{resource}/new` and
 * `/admin/{resource}/{id}/edit` are the resource forms. For create/edit the
 * controller resolves the resource's Page (descriptor) and renders a host
 * template embedding the reactive Form component — it does no querying or writing.
 */
final readonly class AdminController
{
    public function __construct(
        private Environment $twig,
        private ResourceRegistry $registry,
        private DashboardRegistry $dashboards,
        private string $brand,
        private string $pathPrefix,
        private ?DataProviderInterface $dataProvider = null,
    ) {
    }

    /**
     * The panel root (`/admin`): the dashboard registered at the root slug, or the
     * built-in {@see DefaultDashboard} welcome when none claims it.
     */
    public function dashboard(): Response
    {
        $dashboard = $this->rootDashboard();
        $this->denyUnless($dashboard->canAccess());

        // Highlight the root dashboard's nav entry (the built-in default included).
        return $this->renderDashboard($dashboard, $dashboard->getSlug());
    }

    /**
     * Dispatch `/admin/{slug}` to a dashboard or a resource list — dashboards win
     * (slugs are unique across both, see {@see assertNoSlugCollisions()}).
     */
    public function page(string $slug): Response
    {
        if ($this->dashboards->hasSlug($slug)) {
            $dashboard = $this->dashboards->getBySlug($slug);
            $this->denyUnless($dashboard->canAccess());

            return $this->renderDashboard($dashboard, $slug);
        }

        return $this->resourceList($slug);
    }

    public function create(string $resource): Response
    {
        $resourceObject = $this->requireResource($resource);
        $this->denyUnless($resourceObject->canAccess() && $resourceObject->canCreate());
        $page = $resourceObject->resolvePage('create');
        $context = $this->pageContext($resourceObject, $resource);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
            'heading' => $page?->getHeading($context) ?? 'New '.$resourceObject->getSingularLabel(),
            'subheading' => $page?->getSubheading($context),
            'entityId' => null,
            'redirectUrl' => $page?->getRedirectUrl($context),
        ]);
    }

    public function edit(string $resource, string $id): Response
    {
        $resourceObject = $this->requireResource($resource);
        $this->denyUnless($resourceObject->canAccess());

        if (null !== $this->dataProvider) {
            // Scoped resolution: an id outside the resource's scope is a 404 here,
            // the same as it is absent from the list.
            $record = $this->dataProvider->find(
                $resourceObject->getEntityClass(),
                $id,
                $resourceObject->scopeFilters(),
            );
            if (null === $record) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
            }
            $this->denyUnless($resourceObject->canEdit($record));
        }

        $page = $resourceObject->resolvePage('edit');
        $context = $this->pageContext($resourceObject, $resource, $id);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
            'heading' => $page?->getHeading($context) ?? 'Edit '.$resourceObject->getSingularLabel(),
            'subheading' => $page?->getSubheading($context),
            'entityId' => $id,
            'redirectUrl' => $page?->getRedirectUrl($context),
        ]);
    }

    /**
     * The read-only View screen for one record (VIEW-01). Opt-in: a resource that
     * registers no `'view'` page has no View screen, so the bare record URL 404s.
     */
    public function view(string $resource, string $id): Response
    {
        $resourceObject = $this->requireResource($resource);
        $this->denyUnless($resourceObject->canAccess());

        $page = $resourceObject->resolvePage('view');
        if (null === $page) {
            throw new NotFoundHttpException(\sprintf('The "%s" resource has no view screen.', $resource));
        }

        $record = null;
        if (null !== $this->dataProvider) {
            $record = $this->dataProvider->find(
                $resourceObject->getEntityClass(),
                $id,
                $resourceObject->scopeFilters(),
            );
            if (null === $record) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
            }
            $this->denyUnless($resourceObject->canView($record));
        }

        $context = $this->pageContext($resourceObject, $resource, $id);

        // M1 renders link header actions only (the Edit link); server-driven header
        // actions (Delete) need an action host and land with the M4 nav/actions work.
        $canEdit = null === $record || $resourceObject->canEdit($record);
        $editUrl = ($canEdit && null !== $resourceObject->resolvePage('edit')) ? $context->editUrl($id) : null;

        return $this->render('@Atrium/admin/view_page.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
            'heading' => $page->getHeading($context),
            'subheading' => $page->getSubheading($context),
            'editUrl' => $editUrl,
            'schema' => $resourceObject->resolveViewSchema(),
            'record' => $record,
            'entityId' => $id,
        ]);
    }

    private function resourceList(string $resource): Response
    {
        $resourceObject = $this->requireResource($resource);
        // Every page gates on canAccess() (the resource-level gate) plus its own
        // ability — here canViewAny(); create adds canCreate(), edit canEdit().
        // canAccess() defaults to canViewAny(), so by default they coincide.
        $this->denyUnless($resourceObject->canAccess() && $resourceObject->canViewAny());
        $page = $resourceObject->resolvePage('index');
        $context = $this->pageContext($resourceObject, $resource);

        return $this->render('@Atrium/admin/resource.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
            'heading' => $page?->getHeading($context) ?? $resourceObject->getLabel(),
            'subheading' => $page?->getSubheading($context),
            'headerWidgets' => $resourceObject->resolveHeaderWidgets($context),
            'footerWidgets' => $resourceObject->resolveFooterWidgets($context),
        ]);
    }

    private function pageContext(AdminResource $resource, string $slug, ?string $entityId = null): PageContext
    {
        return new PageContext(
            $slug,
            $this->pathPrefix,
            $entityId,
            $resource->getSingularLabel(),
            $resource->getLabel(),
        );
    }

    private function renderDashboard(Dashboard $dashboard, ?string $activeSlug): Response
    {
        return $this->render('@Atrium/admin/dashboard.html.twig', [
            'panel' => $this->panel($activeSlug),
            'dashboard' => $dashboard,
            'configuration' => $dashboard->dashboard(new DashboardConfiguration()),
        ]);
    }

    /**
     * The dashboard registered at the root slug, or the built-in default.
     */
    private function rootDashboard(): Dashboard
    {
        return $this->dashboards->hasSlug(DefaultDashboard::ROOT_SLUG)
            ? $this->dashboards->getBySlug(DefaultDashboard::ROOT_SLUG)
            : new DefaultDashboard();
    }

    private function denyUnless(bool $allowed): void
    {
        if (!$allowed) {
            throw new AccessDeniedHttpException('You are not allowed to access this resource.');
        }
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
     * @return array{brand: string, pathPrefix: string, dashboards: list<array{slug: string, label: string, group: string|null, icon: string|null, url: string, active: bool, badge: string|null, badgeColor: string, sort: int}>, resources: list<array{slug: string, label: string, group: string|null, icon: string|null, url: string, active: bool, badge: string|null, badgeColor: string, sort: int}>}
     */
    private function panel(?string $activeSlug = null): array
    {
        $this->assertNoSlugCollisions();

        $resources = [];
        foreach ($this->registry->all() as $resource) {
            // Only list resources the user can reach and that opt into the menu.
            if (!$resource->canAccess() || !$resource->shouldRegisterNavigation()) {
                continue;
            }
            $resources[] = $this->resourceNavItem($resource, $activeSlug);
        }
        usort($resources, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        $dashboards = [];
        foreach ($this->dashboards->all() as $dashboard) {
            if (!$dashboard->canAccess() || !$dashboard->shouldRegisterNavigation()) {
                continue;
            }
            $dashboards[] = $this->dashboardNavItem($dashboard, $activeSlug);
        }
        usort($dashboards, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        // Always surface a dashboard entry: when the app defines none, show the
        // built-in default (the /admin home).
        if ([] === $dashboards) {
            $dashboards[] = $this->dashboardNavItem(new DefaultDashboard(), $activeSlug);
        }

        // The sidebar keeps dashboards and resources in separate, divided groups;
        // the default dashboard's welcome cards list resources only.
        return [
            'brand' => $this->brand,
            'pathPrefix' => $this->pathPrefix,
            'dashboards' => $dashboards,
            'resources' => $resources,
        ];
    }

    /**
     * @return array{slug: string, label: string, group: string|null, icon: string|null, url: string, active: bool, badge: string|null, badgeColor: string, sort: int}
     */
    private function resourceNavItem(AdminResource $resource, ?string $activeSlug): array
    {
        $slug = $resource->getSlug();

        return [
            'slug' => $slug,
            'label' => $resource->getLabel(),
            'group' => $resource->getNavigationGroup(),
            'icon' => $resource->getNavigationIcon(),
            'url' => rtrim($this->pathPrefix, '/').'/'.$slug,
            'active' => $slug === $activeSlug,
            'badge' => $resource->getNavigationBadge(),
            'badgeColor' => $resource->getNavigationBadgeColor(),
            'sort' => $resource->getNavigationSort() ?? \PHP_INT_MAX,
        ];
    }

    /**
     * @return array{slug: string, label: string, group: string|null, icon: string|null, url: string, active: bool, badge: string|null, badgeColor: string, sort: int}
     */
    private function dashboardNavItem(Dashboard $dashboard, ?string $activeSlug): array
    {
        $slug = $dashboard->getSlug();
        // The root-slug dashboard is served at /admin, not /admin/{slug}.
        $base = rtrim($this->pathPrefix, '/');
        $url = DefaultDashboard::ROOT_SLUG === $slug ? $base : $base.'/'.$slug;

        return [
            'slug' => $slug,
            'label' => $dashboard->getNavigationLabel(),
            'group' => $dashboard->getNavigationGroup(),
            'icon' => $dashboard->getNavigationIcon(),
            'url' => $url,
            'active' => $slug === $activeSlug,
            'badge' => $dashboard->getNavigationBadge(),
            'badgeColor' => $dashboard->getNavigationBadgeColor(),
            'sort' => $dashboard->getNavigationSort() ?? \PHP_INT_MAX,
        ];
    }

    /**
     * Fail fast (PNL-07): a slug used by both a dashboard and a resource would
     * silently shadow the resource, since `/admin/{slug}` checks dashboards first.
     */
    private function assertNoSlugCollisions(): void
    {
        foreach ($this->dashboards->all() as $dashboard) {
            $slug = $dashboard->getSlug();
            if ($this->registry->hasSlug($slug)) {
                throw new \LogicException(\sprintf('Slug "%s" is used by both dashboard %s and a resource; slugs must be unique across dashboards and resources.', $slug, $dashboard::class));
            }
        }
    }
}
