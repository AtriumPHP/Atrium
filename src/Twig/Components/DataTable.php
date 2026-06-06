<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\Action\NestedActionContext;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Page\PageContext;
use Atrium\Relation\ParentRelationResolver;
use Atrium\Relation\ResolvedParentRelation;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Table\TableConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * The reactive list table (TBL-01..08).
 *
 * State and presentation live in {@see AbstractRecordTable}; this component binds
 * that core to a resource's list: rows come from the {@see DataProviderInterface}
 * within the resource's {@see AdminResource::scopeQuery()}, header actions from the
 * list page, and row links from the table's configured record-URL target.
 */
#[AsLiveComponent(name: 'Atrium:DataTable', template: '@Atrium/components/data_table.html.twig')]
final class DataTable extends AbstractRecordTable
{
    #[LiveProp]
    public string $resource = '';

    /** When set, the table is the nested index of a child under this parent id. */
    #[LiveProp]
    public ?string $parentId = null;

    private ?ResolvedParentRelation $resolvedParent = null;

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly ParentRelationResolver $parentResolver,
        DataWriterInterface $writer,
        PropertyAccessorInterface $accessor,
    ) {
        parent::__construct($writer, $accessor);
    }

    public function mount(string $resource, string $pathPrefix = '', ?int $perPage = null, ?string $parentId = null): void
    {
        $this->resource = $resource;
        $this->pathPrefix = $pathPrefix;
        $this->parentId = $parentId;
        // An explicit mount arg wins; otherwise take the resource's configured size.
        $this->perPage = $perPage ?? $this->tableConfig()->getPerPage();
    }

    /**
     * The resolved nesting when this table is a child's nested index (its parentId
     * is set), else null. Validated lazily by the {@see ParentRelationResolver}.
     */
    private function nestedParent(): ?ResolvedParentRelation
    {
        if (null === $this->parentId || '' === $this->parentId) {
            return null;
        }

        return $this->resolvedParent ??= $this->parentResolver->resolve($this->resource());
    }

    protected function resource(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }

    /**
     * @return class-string
     */
    protected function entityClass(): string
    {
        return $this->resource()->getEntityClass();
    }

    protected function makeTableConfiguration(): TableConfiguration
    {
        return $this->resource()->table(TableConfiguration::make());
    }

    protected function fetchRecords(DataQuery $query): iterable
    {
        return $this->dataProvider->fetch($this->entityClass(), $query);
    }

    protected function countRecords(DataQuery $query): int
    {
        return $this->dataProvider->count($this->entityClass(), $query);
    }

    /**
     * Resolve a single record by id within the resource's scope — an id outside
     * {@see AdminResource::scopeQuery()} resolves to null, so a forged action
     * request cannot reach a record the list would never show.
     */
    protected function findRecord(string $id): ?object
    {
        $filters = $this->resource()->scopeFilters();
        // In nested mode also scope by the parent FK, so a forged action id for a
        // child of another parent resolves to null (no cross-parent mutation).
        $parent = $this->nestedParent();
        if (null !== $parent) {
            $filters[$parent->foreignKey] = $this->parentId;
        }

        return $this->dataProvider->find(
            $this->entityClass(),
            $id,
            $filters,
            $this->resource()->getIdentifierField(),
        );
    }

    /**
     * The resource's header actions (shown above the table).
     *
     * @return list<Action>
     */
    public function getHeaderActions(): array
    {
        return $this->resource()->resolveHeaderActions('index', $this->pageContext());
    }

    protected function extraFilters(): array
    {
        $parent = $this->nestedParent();

        return null === $parent ? [] : [$parent->foreignKey => (string) $this->parentId];
    }

    protected function actionContext(?string $id): ActionContext
    {
        $parent = $this->nestedParent();
        if (null === $parent) {
            return new ActionContext($this->pathPrefix, $this->resource, $id ?? '');
        }

        return new NestedActionContext(
            $this->pathPrefix,
            $parent->parentResource->getSlug(),
            (string) $this->parentId,
            $this->resource,
            $id ?? '',
        );
    }

    /**
     * The URL a row click navigates to, per the table's configured target
     * (`recordUrl()`): a custom closure's result, or the bare view / edit URL —
     * auto-suppressed when the resource has no such page or the user lacks the
     * ability for this record. `null` leaves the row non-clickable.
     */
    protected function rowUrl(object $record, ActionContext $context): ?string
    {
        $target = $this->tableConfig()->getRecordUrl();

        if ($target instanceof \Closure) {
            $url = $target($record);

            // A custom URL may be derived from record data, so guard its scheme:
            // a relative path or an http(s)/mailto/tel link is fine; anything else
            // (`javascript:`, `data:`, …) is dropped so it can never become an
            // executable href.
            return \is_string($url) ? self::safeUrl($url) : null;
        }

        return match ($target) {
            'view' => $this->canReachPage('view', $record) ? $context->recordRootUrl() : null,
            'edit' => $this->canReachPage('edit', $record) ? $context->recordUrl('edit') : null,
            default => null,
        };
    }

    private function pageContext(): PageContext
    {
        $resource = $this->resource();

        return new PageContext(
            $this->resource,
            $this->pathPrefix,
            null,
            $resource->getSingularLabel(),
            $resource->getLabel(),
        );
    }

    /**
     * Allow a relative/anchor/query URL or an explicitly safe scheme; reject any
     * other `scheme:` so a record-derived row URL cannot carry `javascript:` and
     * the like. Mirrors the guard on view {@see \Atrium\View\Entry} links.
     */
    private static function safeUrl(string $url): ?string
    {
        if ('' === trim($url)) {
            return null;
        }

        $candidate = ltrim($url);
        if (preg_match('#^(?:https?:|mailto:|tel:|/|\#|\?|\.)#i', $candidate)) {
            return $url;
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*:#i', $candidate) ? null : $url;
    }

    /**
     * Whether the resource exposes the given page and the user may reach it for
     * this record (the page exists and its ability passes).
     */
    private function canReachPage(string $action, object $record): bool
    {
        return null !== $this->resource()->resolvePage($action)
            && $this->resource()->can($action, $record);
    }
}
