<?php

declare(strict_types=1);

namespace Atrium\Controller;

use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardConfiguration;
use Atrium\Dashboard\DashboardRegistry;
use Atrium\Dashboard\DefaultDashboard;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\Page\PageContext;
use Atrium\Relation\ParentRelationResolver;
use Atrium\Relation\ResolvedParentRelation;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
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
        private ParentRelationResolver $parentResolver,
        private PropertyAccessorInterface $accessor,
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
                $resourceObject->getIdentifierField(),
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
                $resourceObject->getIdentifierField(),
            );
            if (null === $record) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
            }
            $this->denyUnless($resourceObject->canView($record));
        }

        $context = $this->pageContext($resourceObject, $resource, $id);

        return $this->render('@Atrium/admin/view_page.html.twig', [
            'panel' => $this->panel($resource),
            'resource' => $resourceObject,
            'heading' => $page->getHeading($context),
            'subheading' => $page->getSubheading($context),
            'schema' => $resourceObject->resolveViewSchema(),
            'headerWidgets' => $resourceObject->resolveViewHeaderWidgets($context),
            'footerWidgets' => $resourceObject->resolveViewFooterWidgets($context),
            'record' => $record,
            'entityId' => $id,
        ]);
    }

    /**
     * Resolve a nested request: the parent resource + its (scoped) record and the
     * child resource, asserting the URL's nesting is the one the child declares.
     * Any failure — unregistered/forbidden resource, undeclared nesting, or an
     * out-of-scope parent — is a 404/403, never a leak.
     *
     * @return array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation}
     */
    private function resolveNested(string $parentResource, string $parentId, string $resource): array
    {
        $parent = $this->requireResource($parentResource);
        $child = $this->requireResource($resource);
        $this->denyUnless($parent->canAccess() && $child->canAccess());

        // The child must declare it is nested under exactly this parent + relation.
        // A mismatch (or a non-nested child) is a 404: this URL shape isn't offered.
        if (null === $child->parent()) {
            throw new NotFoundHttpException(\sprintf('Resource "%s" is not a nested resource.', $resource));
        }
        $resolved = $this->parentResolver->resolve($child);
        if ($resolved->parentResource->getSlug() !== $parent->getSlug()) {
            throw new NotFoundHttpException(\sprintf('Resource "%s" is not nested under "%s".', $resource, $parentResource));
        }

        $parentRecord = null;
        if (null !== $this->dataProvider) {
            $parentRecord = $this->dataProvider->find(
                $parent->getEntityClass(),
                $parentId,
                $parent->scopeFilters(),
                $parent->getIdentifierField(),
            );
            if (null === $parentRecord) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $parentResource, $parentId));
            }
        }

        return ['parent' => $parent, 'parentRecord' => $parentRecord, 'child' => $child, 'resolved' => $resolved];
    }

    /**
     * Resolve the child record scoped to BOTH its own scopeQuery and the parent FK
     * (a child of another parent — a forged id — is a 404, never reachable here).
     */
    private function findNestedRecord(AdminResource $child, ResolvedParentRelation $resolved, string $parentId, string $id): ?object
    {
        if (null === $this->dataProvider) {
            return null;
        }

        // Assignment, not union: the URL's parent segment must always win, even if
        // the child's scopeQuery() also constrains the FK column (a `+` union would
        // keep the scope value and silently drop the parent segment).
        $filters = $child->scopeFilters();
        $filters[$resolved->foreignKey] = $parentId;

        return $this->dataProvider->find($child->getEntityClass(), $id, $filters, $child->getIdentifierField());
    }

    public function nestedView(string $parentResource, string $parentId, string $resource, string $id): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];

        $page = $child->resolvePage('view');
        if (null === $page) {
            throw new NotFoundHttpException(\sprintf('The "%s" resource has no view screen.', $resource));
        }

        $record = $this->findNestedRecord($child, $ctx['resolved'], $parentId, $id);
        if (null !== $this->dataProvider) {
            if (null === $record) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
            }
            $this->denyUnless($child->canView($record));
        }

        $context = $this->nestedPageContext($ctx, $id);

        return $this->render('@Atrium/admin/view_page.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page->getHeading($context),
            'subheading' => $page->getSubheading($context),
            'schema' => $child->resolveViewSchema(),
            'headerWidgets' => $child->resolveViewHeaderWidgets($context),
            'footerWidgets' => $child->resolveViewFooterWidgets($context),
            'record' => $record,
            'entityId' => $id,
            'parentResourceSlug' => $parentResource,
            'parentRecordId' => $parentId,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'backUrl' => $this->nestedIndexUrl($ctx, $parentId),
        ]);
    }

    public function nestedCreate(string $parentResource, string $parentId, string $resource): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];
        $this->denyUnless($child->canCreate());

        $page = $child->resolvePage('create');
        $context = $this->nestedPageContext($ctx, null);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page?->getHeading($context) ?? 'New '.$child->getSingularLabel(),
            'subheading' => $page?->getSubheading($context),
            'entityId' => null,
            'redirectUrl' => $page?->getRedirectUrl($context) ?? $this->nestedIndexUrl($ctx, $parentId),
            // Preset the FK so the created child is linked to this parent in one save.
            // Read the parent's *typed* id from the resolved record (not the raw URL
            // string) so PropertyAccess can set an int-typed FK (e.g. Task.projectId);
            // fall back to the raw URL id when no data provider resolved a parent, so
            // the FK is never silently preset to null (which would orphan the child).
            'presetValues' => [$ctx['resolved']->foreignKey => $this->parentScalarId($ctx) ?? $parentId],
            'parentResourceSlug' => $parentResource,
            'parentRecordId' => $parentId,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'backUrl' => $this->nestedIndexUrl($ctx, $parentId),
        ]);
    }

    public function nestedEdit(string $parentResource, string $parentId, string $resource, string $id): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];

        $record = $this->findNestedRecord($child, $ctx['resolved'], $parentId, $id);
        if (null !== $this->dataProvider) {
            if (null === $record) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
            }
            $this->denyUnless($child->canEdit($record));
        }

        $page = $child->resolvePage('edit');
        $context = $this->nestedPageContext($ctx, $id);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page?->getHeading($context) ?? 'Edit '.$child->getSingularLabel(),
            'subheading' => $page?->getSubheading($context),
            'entityId' => $id,
            // After saving the child, return to its nested index under this parent.
            'redirectUrl' => $page?->getRedirectUrl($context) ?? $this->nestedIndexUrl($ctx, $parentId),
            // Re-apply the parent FK on save (as on create) so a child can't be
            // re-parented through a crafted form submit — it stays under this parent.
            'presetValues' => [$ctx['resolved']->foreignKey => $this->parentScalarId($ctx)],
            'parentResourceSlug' => $parentResource,
            'parentRecordId' => $parentId,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'backUrl' => $this->nestedIndexUrl($ctx, $parentId),
        ]);
    }

    public function nestedIndex(string $parentResource, string $parentId, string $resource): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];
        $this->denyUnless($child->canViewAny());

        $page = $child->resolvePage('index');
        $context = $this->nestedPageContext($ctx, null);

        return $this->render('@Atrium/admin/resource.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page?->getHeading($context) ?? $child->getLabel(),
            'subheading' => $page?->getSubheading($context),
            'headerWidgets' => $child->resolveHeaderWidgets($context),
            'footerWidgets' => $child->resolveFooterWidgets($context),
            'parentRecordId' => $parentId,           // flips the DataTable into nested mode
            'parentResourceSlug' => $parentResource,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'createUrl' => $this->nestedIndexUrl($ctx, $parentId).'/new',
        ]);
    }

    /**
     * @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx
     */
    private function nestedPageContext(array $ctx, ?string $entityId): PageContext
    {
        $parentRecords = null !== $ctx['parentRecord'] ? [$ctx['parentRecord']] : [];

        return new PageContext(
            $ctx['child']->getSlug(),
            $this->pathPrefix,
            $entityId,
            $ctx['child']->getSingularLabel(),
            $ctx['child']->getLabel(),
            parentResourceSlug: $ctx['parent']->getSlug(),
            parentRecordId: null !== $ctx['parentRecord'] ? $this->stringId($ctx['parent'], $ctx['parentRecord']) : null,
            parentRecords: $parentRecords,
        );
    }

    /**
     * The parent's identifier as a scalar of its *native* type (int/string), read
     * from the already-resolved parent record so a preset FK matches the child
     * property's type. Null when no data provider resolved a parent (no-DB install).
     *
     * @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx
     */
    private function parentScalarId(array $ctx): int|string|null
    {
        if (null === $ctx['parentRecord']) {
            return null;
        }

        $id = $this->accessor->getValue($ctx['parentRecord'], $ctx['parent']->getIdentifierField());

        return \is_int($id) || \is_string($id) ? $id : null;
    }

    /**
     * @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx
     */
    private function nestedIndexUrl(array $ctx, string $parentId): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$ctx['parent']->getSlug().'/'.rawurlencode($parentId).'/'.$ctx['child']->getSlug();
    }

    /**
     * Breadcrumb trail for a nested page: the parent resource index, the parent
     * record (titled via the ParentRelation's recordTitle / the parent identifier),
     * then the child resource index. The current record is the page <h1>, not a crumb.
     *
     * @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx
     *
     * @return list<array{label: string, url: ?string}>
     */
    private function nestedBreadcrumbs(array $ctx): array
    {
        $parent = $ctx['parent'];
        $base = rtrim($this->pathPrefix, '/');
        $crumbs = [
            ['label' => $parent->getLabel(), 'url' => $base.'/'.$parent->getSlug()],
        ];

        $record = $ctx['parentRecord'];
        if (null !== $record) {
            $parentId = $this->stringId($parent, $record);
            $titleValue = $this->accessor->getValue($record, $ctx['resolved']->recordTitleAttribute);
            $title = \is_scalar($titleValue) ? (string) $titleValue : $parentId;

            if ('' === $parentId) {
                // A non-scalar parent id can't form a URL; show labels only.
                $crumbs[] = ['label' => $title, 'url' => null];
                $crumbs[] = ['label' => $ctx['child']->getLabel(), 'url' => null];
            } else {
                $recordRoot = $base.'/'.$parent->getSlug().'/'.rawurlencode($parentId);
                $crumbs[] = ['label' => $title, 'url' => $this->parentRecordUrl($parent, $recordRoot)];
                $crumbs[] = ['label' => $ctx['child']->getLabel(), 'url' => $recordRoot.'/'.$ctx['child']->getSlug()];
            }
        }

        return $crumbs;
    }

    /**
     * Where the parent-record breadcrumb crumb links: the parent's View page (the
     * bare record URL) if it has one, else its Edit page, else nowhere (a view-only
     * or list-only parent leaves the crumb unlinked rather than pointing at a 404).
     */
    private function parentRecordUrl(AdminResource $parent, string $recordRoot): ?string
    {
        if (null !== $parent->resolvePage('view')) {
            return $recordRoot;
        }
        if (null !== $parent->resolvePage('edit')) {
            return $recordRoot.'/edit';
        }

        return null;
    }

    private function stringId(AdminResource $resource, object $record): string
    {
        $id = $this->accessor->getValue($record, $resource->getIdentifierField());

        return \is_scalar($id) ? (string) $id : '';
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
