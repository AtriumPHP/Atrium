<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\Action\ActionContract;
use Atrium\Action\Concern\InteractsWithActions;
use Atrium\Action\Concern\InteractsWithBulkActions;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\Filter\Filter;
use Atrium\Table\TableConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PreReRender;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * @internal shared core for the reactive record tables (REL-03). Owns all table
 * presentation, state and interaction — columns, search, sort, pagination,
 * filters, row/header/bulk actions — driving the shared `_record_table.html.twig`
 * partial. Subclasses provide only the data source and the action/URL context:
 * {@see DataTable} reads a resource's list through the {@see \Atrium\DataProvider\DataProviderInterface},
 * {@see RelationManager} reads a parent record's related rows through the
 * {@see \Atrium\DataProvider\RelationDataProvider}.
 */
abstract class AbstractRecordTable
{
    use DefaultActionTrait;
    use InteractsWithActions;
    use InteractsWithBulkActions;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $search = '';

    #[LiveProp(writable: true)]
    public ?string $sortField = null;

    #[LiveProp(writable: true)]
    public string $sortDirection = DataQuery::SORT_ASC;

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true, onUpdated: 'onPerPageUpdated')]
    public int $perPage = 10;

    /**
     * Selected filter values, keyed by filter name. Writable so the filter-bar
     * controls bind to it; non-string or unknown-name entries are ignored when
     * resolving conditions, so a forged value cannot inject a condition.
     *
     * @var array<string, mixed>
     */
    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public array $filterValues = [];

    /**
     * Panel path prefix, kept in state so record-action URLs survive re-renders.
     */
    #[LiveProp]
    public string $pathPrefix = '';

    private ?TableConfiguration $tableConfig = null;

    /** @var list<string>|null */
    private ?array $pageIds = null;

    /** @var list<object>|null */
    private ?array $pageRecords = null;

    /** @var array<string, scalar|bool|null>|null */
    private ?array $resolvedFilters = null;

    private ?int $totalCount = null;

    public function __construct(
        protected readonly DataWriterInterface $writer,
        protected readonly PropertyAccessorInterface $accessor,
    ) {
    }

    // -- Data-source & context seams (subclass-provided) -------------------

    abstract protected function resource(): AdminResource;

    /** @return class-string */
    abstract protected function entityClass(): string;

    /** @return iterable<object> */
    abstract protected function fetchRecords(DataQuery $query): iterable;

    abstract protected function countRecords(DataQuery $query): int;

    abstract protected function findRecord(string $id): ?object;

    /**
     * The visible header actions for this table (subject-less).
     *
     * @return list<Action>
     */
    abstract public function getHeaderActions(): array;

    abstract protected function actionContext(?string $id): ActionContext;

    abstract protected function rowUrl(object $record, ActionContext $context): ?string;

    abstract protected function makeTableConfiguration(): TableConfiguration;

    // -- Interaction -------------------------------------------------------

    #[LiveAction]
    public function sort(#[LiveArg] string $field): void
    {
        if (!$this->isSortable($field)) {
            return;
        }

        // Toggle against the *active* sort, so clicking the column shown as sorted
        // (even when that comes from the configured default) flips its direction.
        if ($this->getActiveSortField() === $field) {
            $this->sortDirection = DataQuery::SORT_DESC === $this->getActiveSortDirection()
                ? DataQuery::SORT_ASC
                : DataQuery::SORT_DESC;
        } else {
            $this->sortDirection = DataQuery::SORT_ASC;
        }

        $this->sortField = $field;
        $this->page = 1;
    }

    #[LiveAction]
    public function gotoPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, min($page, $this->getPageCount()));
    }

    /**
     * Clamp a client-supplied page size to a configured choice (so a forged
     * value cannot request an arbitrarily large page) and return to page one.
     */
    public function onPerPageUpdated(): void
    {
        $options = $this->tableConfig()->getPerPageOptions();
        $allowed = [] !== $options ? $options : [$this->tableConfig()->getPerPage()];
        if (!\in_array($this->perPage, $allowed, true)) {
            $this->perPage = $this->tableConfig()->getPerPage();
        }

        $this->page = 1;
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    /**
     * Keep the page within range before every render, so narrowing the result set
     * (a filter or a search) never leaves the table stranded on an empty page —
     * independent of which interaction changed the state.
     */
    #[PreReRender]
    public function clampPage(): void
    {
        $this->page = max(1, min($this->page, $this->getPageCount()));
    }

    /**
     * Clear all filter selections and return to the first page.
     */
    #[LiveAction]
    public function resetFilters(): void
    {
        $this->filterValues = [];
        $this->page = 1;
    }

    /**
     * The resolved table configuration (columns + actions), read once per request.
     */
    protected function tableConfig(): TableConfiguration
    {
        return $this->tableConfig ??= $this->makeTableConfiguration();
    }

    /**
     * The visible columns (a column hidden via `Column::visible(false)` is dropped
     * from the header, cells, search and sort).
     *
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return array_values(array_filter(
            $this->tableConfig()->getColumns(),
            static fn (Column $column): bool => $column->isVisible(),
        ));
    }

    /**
     * The resource's record actions (per-row actions and action groups).
     *
     * @return list<ActionContract>
     */
    public function getRecordActions(): array
    {
        return $this->tableConfig()->getRecordActions();
    }

    public function hasRecordActions(): bool
    {
        return [] !== $this->getRecordActions();
    }

    public function hasHeaderActions(): bool
    {
        return [] !== $this->getHeaderActions();
    }

    /**
     * Render-ready descriptors for the visible header actions (subject-less).
     *
     * @return list<array<string, mixed>>
     */
    public function getHeaderActionViews(): array
    {
        return $this->standaloneViews($this->getHeaderActions());
    }

    /**
     * The resource's bulk actions (run against the selection).
     *
     * @return list<Action>
     */
    public function getBulkActions(): array
    {
        return $this->tableConfig()->getBulkActions();
    }

    public function hasBulkActions(): bool
    {
        return [] !== $this->getBulkActions();
    }

    /**
     * Render-ready descriptors for the visible bulk actions (subject-less).
     *
     * @return list<array<string, mixed>>
     */
    public function getBulkActionViews(): array
    {
        return $this->standaloneViews($this->getBulkActions());
    }

    /**
     * @return list<Filter>
     */
    public function getFilters(): array
    {
        return $this->tableConfig()->getFilters();
    }

    public function hasFilters(): bool
    {
        return [] !== $this->getFilters();
    }

    /**
     * Whether any filter currently narrows the result set.
     */
    public function hasActiveFilters(): bool
    {
        return [] !== $this->resolvedFilters();
    }

    /**
     * Render-ready descriptors for the filter-bar controls, each carrying its
     * current value.
     *
     * @return list<array<string, mixed>>
     */
    public function getFilterViews(): array
    {
        $views = [];
        foreach ($this->getFilters() as $filter) {
            $views[] = $filter->toView($this->filterValue($filter->getName()));
        }

        return $views;
    }

    protected function findAction(string $name): ?Action
    {
        foreach ($this->getRecordActions() as $item) {
            foreach ($item->flatten() as $action) {
                if ($action->getName() === $name) {
                    return $action;
                }
            }
        }

        return null;
    }

    protected function canExecuteAction(Action $action, string $subjectId): bool
    {
        $record = $this->findRecord($subjectId);

        return null !== $record && $action->isVisibleFor($record) && $this->actionAuthorized($action, $record);
    }

    // A table action never redirects (it mutates the list in place), so this
    // narrows the trait's ?Response to null.
    protected function executeAction(Action $action, string $subjectId): null
    {
        $handler = $action->getHandler();
        $record = $this->findRecord($subjectId);
        if (null === $handler || null === $record
            || !$action->isVisibleFor($record) || !$this->actionAuthorized($action, $record)) {
            return null;
        }

        $deletes = 'delete' === $action->getAbility();
        $name = $action->getName();
        // Run the action atomically: the lifecycle hooks and the handler commit
        // together or not at all.
        $this->writer->transactional(function () use ($handler, $record, $deletes, $name): void {
            $this->resource()->beforeAction($name, $record);
            if ($deletes) {
                $this->resource()->beforeDelete($record);
            }
            $handler($record, $this->writer);
            if ($deletes) {
                $this->resource()->afterDelete($record);
            }
            $this->resource()->afterAction($name, $record);
        });

        // The row set may have shrunk (e.g. a delete) — refresh the count and
        // keep the page in range.
        $this->refreshRecords();

        return null;
    }

    /**
     * Drop the per-request record/count caches so the next render re-reads the
     * data source, and keep the page within the (possibly smaller) range. Called
     * after a mutating action or when the host signals the data changed.
     */
    protected function refreshRecords(): void
    {
        $this->totalCount = null;
        $this->pageIds = null;
        $this->pageRecords = null;
        $this->page = min($this->page, $this->getPageCount());
    }

    /**
     * The Live action a row click triggers (with the record id), or null to use
     * {@see rowUrl()} navigation instead. The base table navigates; a subclass
     * can return an action name (e.g. open an edit modal) to handle clicks itself.
     */
    public function getRowAction(): ?string
    {
        return null;
    }

    /**
     * Whether the action is allowed for the subject: an action with an ability is
     * gated by the resource's {@see AdminResource::can()}; one without is allowed.
     */
    protected function actionAuthorized(Action $action, ?object $record): bool
    {
        $ability = $action->getAbility();

        return null === $ability || $this->resource()->can($ability, $record);
    }

    /**
     * Authorize a subject-less (header / bulk) action: only panel-level abilities
     * (`create`, `viewAny`) can be judged without a record. Record-scoped
     * abilities (`edit`, `delete`, `view`) are deferred to the per-record checks
     * at execution, so e.g. a bulk Delete still shows and acts only on the records
     * the user may delete.
     */
    private function standaloneAuthorized(Action $action): bool
    {
        $ability = $action->getAbility();
        if (null === $ability || !\in_array($ability, ['create', 'viewAny'], true)) {
            return true;
        }

        return $this->resource()->can($ability, null);
    }

    protected function bulkActionAuthorized(Action $action): bool
    {
        // Panel-level abilities are decided here; record-scoped ones are filtered
        // per record in runBulkAction().
        return $this->standaloneAuthorized($action);
    }

    protected function findBulkAction(string $name): ?Action
    {
        foreach ($this->getBulkActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    protected function runBulkAction(Action $action): void
    {
        $handler = $action->getHandler();
        if (null === $handler || !$action->isVisible() || !$this->hasSelection()) {
            return;
        }

        // Only operate on records the action is actually allowed for.
        $records = array_values(array_filter(
            $this->selectedRecords(),
            fn (object $record): bool => $this->actionAuthorized($action, $record),
        ));
        if ([] === $records) {
            return;
        }

        $deletes = 'delete' === $action->getAbility();
        $name = $action->getName();
        // The whole bulk operation is atomic: lifecycle hooks and the handler
        // commit together or roll back together.
        $this->writer->transactional(function () use ($handler, $records, $deletes, $name): void {
            $this->resource()->beforeBulkAction($name, $records);
            if ($deletes) {
                foreach ($records as $record) {
                    $this->resource()->beforeDelete($record);
                }
            }
            $handler($records, $this->writer);
            if ($deletes) {
                foreach ($records as $record) {
                    $this->resource()->afterDelete($record);
                }
            }
            $this->resource()->afterBulkAction($name, $records);
        });

        // The selection has been consumed and the row set may have shrunk.
        $this->clearSelection();
        $this->refreshRecords();
    }

    protected function currentPageIds(): array
    {
        if (null !== $this->pageIds) {
            return $this->pageIds;
        }

        $ids = [];
        foreach ($this->pageRecords() as $record) {
            $id = $this->recordId($record);
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $this->pageIds = $ids;
    }

    /**
     * The current page's records, fetched once per request and shared by the row
     * renderer and the selection helpers (avoids a second identical query).
     *
     * @return list<object>
     */
    private function pageRecords(): array
    {
        if (null !== $this->pageRecords) {
            return $this->pageRecords;
        }

        $records = [];
        foreach ($this->fetchRecords($this->query()) as $record) {
            $records[] = $record;
        }

        return $this->pageRecords = $records;
    }

    protected function totalSelectableCount(): int
    {
        return $this->getTotalCount();
    }

    /**
     * Pre-rendered rows: formatted cell strings plus the record id and the
     * per-record actions resolved (for visibility, URL, style) into render-ready
     * descriptors — a plain action, or a `kind: 'group'` dropdown of them.
     *
     * @return list<array{id: ?string, selected: bool, cells: list<array<string, mixed>>, actions: list<array<string, mixed>>, url: ?string}>
     */
    public function getRows(): array
    {
        $columns = $this->getColumns();
        $items = $this->getRecordActions();
        $rows = [];

        foreach ($this->pageRecords() as $record) {
            $cells = [];
            foreach ($columns as $column) {
                $cells[] = $column->toCell($record, $this->accessor);
            }

            $id = $this->recordId($record);
            $context = $this->actionContext($id);

            $authorize = fn (Action $action): bool => $this->actionAuthorized($action, $record);
            $rowActions = [];
            foreach ($items as $item) {
                if (!$item->isVisibleFor($record)) {
                    continue;
                }
                // A plain action is hidden when unauthorised; a group hides its
                // unauthorised children and is dropped only if none remain.
                if ($item instanceof Action && !$authorize($item)) {
                    continue;
                }
                $view = $item->toView($record, $context, $id, $authorize);
                if ('group' === ($view['kind'] ?? null) && [] === $view['actions']) {
                    continue;
                }
                $rowActions[] = $view;
            }

            $rows[] = [
                'id' => $id,
                'selected' => null !== $id && $this->isRecordSelected($id),
                'cells' => $cells,
                'actions' => $rowActions,
                'url' => $this->rowUrl($record, $context),
            ];
        }

        return $rows;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount ??= $this->countRecords($this->query());
    }

    public function getPageCount(): int
    {
        return max(1, (int) ceil($this->getTotalCount() / max(1, $this->perPage)));
    }

    public function getResourceLabel(): string
    {
        return $this->resource()->getLabel();
    }

    /**
     * The field the table is currently sorted by: the user's explicit sort if any,
     * otherwise the resource's configured default (which need not be a sortable
     * column). Null when neither applies.
     */
    public function getActiveSortField(): ?string
    {
        if (null !== $this->sortField && $this->isSortable($this->sortField)) {
            return $this->sortField;
        }

        return $this->tableConfig()->getDefaultSortField();
    }

    public function getActiveSortDirection(): string
    {
        if (null !== $this->sortField && $this->isSortable($this->sortField)) {
            return $this->sortDirection;
        }

        return $this->tableConfig()->getDefaultSortDirection();
    }

    /**
     * @return list<int>
     */
    public function getPerPageOptions(): array
    {
        return $this->tableConfig()->getPerPageOptions();
    }

    /**
     * Empty-state heading, or a sensible default ("No … found.").
     */
    public function getEmptyHeading(): string
    {
        return $this->tableConfig()->getEmptyHeading() ?? 'No '.strtolower($this->getResourceLabel()).' found.';
    }

    public function getEmptyDescription(): ?string
    {
        return $this->tableConfig()->getEmptyDescription();
    }

    public function getEmptyIcon(): ?string
    {
        return $this->tableConfig()->getEmptyIcon();
    }

    protected function recordId(object $record): ?string
    {
        $idField = $this->resource()->getIdentifierField();
        if (!$this->accessor->isReadable($record, $idField)) {
            return null;
        }

        $id = $this->accessor->getValue($record, $idField);

        return \is_scalar($id) ? (string) $id : null;
    }

    /**
     * Resolve the records the current selection targets — every record matching
     * the query (minus exclusions) in select-all mode, otherwise the explicitly
     * selected ids.
     *
     * @return list<object>
     */
    private function selectedRecords(): array
    {
        if ($this->selectAll) {
            $records = [];
            foreach ($this->fetchRecords($this->allMatchingQuery()) as $record) {
                $id = $this->recordId($record);
                if (null === $id || !\in_array($id, $this->excluded, true)) {
                    $records[] = $record;
                }
            }

            return $records;
        }

        $records = [];
        foreach ($this->selected as $id) {
            $record = $this->findRecord($id);
            if (null !== $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * The current query without pagination, to enumerate every matching record
     * for a select-all bulk action. Like {@see query()}, it is built from
     * {@see resource()}'s scope; a subclass that overrides {@see fetchRecords()}
     * must keep it a query that data source can safely apply.
     */
    protected function allMatchingQuery(): DataQuery
    {
        return $this->resource()->scopeQuery(new DataQuery(
            search: $this->search,
            searchableFields: $this->searchableFields(),
            sortField: $this->getActiveSortField(),
            sortDirection: $this->getActiveSortDirection(),
            offset: 0,
            limit: max(1, $this->getTotalCount()),
            filters: $this->extraFilters() + $this->resolvedFilters(),
        ));
    }

    /**
     * Always-on equality conditions layered under the configured filters (e.g. a
     * nested table's parent foreign key). Empty by default; a subclass narrows the
     * whole table. As the left operand of the union it wins over a user-supplied
     * filter on the same field, so a forged filter value cannot relax the scope.
     *
     * @return array<string, scalar|bool|null>
     */
    protected function extraFilters(): array
    {
        return [];
    }

    /**
     * Subject-less view descriptors for the visible actions among $actions.
     *
     * @param list<Action> $actions
     *
     * @return list<array<string, mixed>>
     */
    private function standaloneViews(array $actions): array
    {
        $context = $this->actionContext(null);

        $views = [];
        foreach ($actions as $action) {
            if ($action->isVisible() && $this->standaloneAuthorized($action)) {
                $views[] = $action->toStandaloneView($context);
            }
        }

        return $views;
    }

    protected function query(): DataQuery
    {
        return $this->resource()->scopeQuery(new DataQuery(
            search: $this->search,
            searchableFields: $this->searchableFields(),
            sortField: $this->getActiveSortField(),
            sortDirection: $this->getActiveSortDirection(),
            offset: (max(1, $this->page) - 1) * $this->perPage,
            limit: $this->perPage,
            filters: $this->extraFilters() + $this->resolvedFilters(),
        ));
    }

    /**
     * Resolve each configured filter's current value into the equality conditions
     * to apply (memoised for the request). Values for unconfigured filter names
     * are ignored. Conditions are a field => value map, so at most one equality
     * per field — two filters over the same field would collapse to the last.
     *
     * @return array<string, scalar|bool|null>
     */
    private function resolvedFilters(): array
    {
        if (null !== $this->resolvedFilters) {
            return $this->resolvedFilters;
        }

        $conditions = [];
        foreach ($this->getFilters() as $filter) {
            foreach ($filter->conditions($this->filterValue($filter->getName())) as $field => $value) {
                $conditions[$field] = $value;
            }
        }

        return $this->resolvedFilters = $conditions;
    }

    private function filterValue(string $name): string
    {
        $value = $this->filterValues[$name] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    protected function searchableFields(): array
    {
        $fields = [];
        foreach ($this->getColumns() as $column) {
            if ($column->isSearchable()) {
                $fields[] = $column->getName();
            }
        }

        return $fields;
    }

    private function isSortable(string $field): bool
    {
        foreach ($this->getColumns() as $column) {
            if ($column->getName() === $field && $column->isSortable()) {
                return true;
            }
        }

        return false;
    }
}
