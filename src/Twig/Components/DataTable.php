<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\Action\ActionContract;
use Atrium\Action\Concern\InteractsWithActions;
use Atrium\Action\Concern\InteractsWithBulkActions;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The reactive list table (TBL-01..08).
 *
 * State lives entirely in LiveProps; every interaction (search, sort, paginate)
 * is a server round-trip that re-renders this component. Reads go through the
 * {@see DataProviderInterface}, so the table is backend-agnostic.
 */
#[AsLiveComponent(name: 'Atrium:DataTable', template: '@Atrium/components/data_table.html.twig')]
final class DataTable
{
    use DefaultActionTrait;
    use InteractsWithActions;
    use InteractsWithBulkActions;

    #[LiveProp]
    public string $resource = '';

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
     * Panel path prefix, kept in state so record-action URLs survive re-renders.
     */
    #[LiveProp]
    public string $pathPrefix = '';

    private ?TableConfiguration $tableConfig = null;

    /** @var list<string>|null */
    private ?array $pageIds = null;

    /** @var list<object>|null */
    private ?array $pageRecords = null;

    private ?int $totalCount = null;

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly DataWriterInterface $writer,
        private readonly PropertyAccessorInterface $accessor,
    ) {
    }

    public function mount(string $resource, string $pathPrefix = '', ?int $perPage = null): void
    {
        $this->resource = $resource;
        $this->pathPrefix = $pathPrefix;
        // An explicit mount arg wins; otherwise take the resource's configured size.
        $this->perPage = $perPage ?? $this->tableConfig()->getPerPage();
    }

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
     * The resolved table configuration (columns + actions), read once per request.
     */
    private function tableConfig(): TableConfiguration
    {
        return $this->tableConfig ??= $this->resource()->table(TableConfiguration::make());
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

    /**
     * The resource's header actions (shown above the table).
     *
     * @return list<Action>
     */
    public function getHeaderActions(): array
    {
        return $this->tableConfig()->getHeaderActions();
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
        $record = $this->dataProvider->find($this->entityClass(), $subjectId);

        return null !== $record && $action->isVisibleFor($record);
    }

    protected function executeAction(Action $action, string $subjectId): void
    {
        $handler = $action->getHandler();
        $record = $this->dataProvider->find($this->entityClass(), $subjectId);
        if (null === $handler || null === $record || !$action->isVisibleFor($record)) {
            return;
        }

        $handler($record, $this->writer);

        // The row set may have shrunk (e.g. a delete) — refresh the count and
        // keep the page in range.
        $this->totalCount = null;
        $this->pageIds = null;
        $this->pageRecords = null;
        $this->page = min($this->page, $this->getPageCount());
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

        $records = $this->selectedRecords();
        if ([] === $records) {
            return;
        }

        $handler($records, $this->writer);

        // The selection has been consumed and the row set may have shrunk.
        $this->totalCount = null;
        $this->pageIds = null;
        $this->pageRecords = null;
        $this->clearSelection();
        $this->page = min($this->page, $this->getPageCount());
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
        foreach ($this->dataProvider->fetch($this->entityClass(), $this->query()) as $record) {
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
     * @return list<array{id: ?string, selected: bool, cells: list<array<string, mixed>>, actions: list<array<string, mixed>>}>
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
            $context = new ActionContext($this->pathPrefix, $this->resource, $id ?? '');

            $rowActions = [];
            foreach ($items as $item) {
                if ($item->isVisibleFor($record)) {
                    $rowActions[] = $item->toView($record, $context, $id);
                }
            }

            $rows[] = [
                'id' => $id,
                'selected' => null !== $id && $this->isRecordSelected($id),
                'cells' => $cells,
                'actions' => $rowActions,
            ];
        }

        return $rows;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount ??= $this->dataProvider->count($this->entityClass(), $this->query());
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

    private function resource(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }

    private function recordId(object $record): ?string
    {
        if (!$this->accessor->isReadable($record, 'id')) {
            return null;
        }

        $id = $this->accessor->getValue($record, 'id');

        return \is_scalar($id) ? (string) $id : null;
    }

    /**
     * @return class-string
     */
    private function entityClass(): string
    {
        return $this->resource()->getEntityClass();
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
        $class = $this->entityClass();

        if ($this->selectAll) {
            $records = [];
            foreach ($this->dataProvider->fetch($class, $this->allMatchingQuery()) as $record) {
                $id = $this->recordId($record);
                if (null === $id || !\in_array($id, $this->excluded, true)) {
                    $records[] = $record;
                }
            }

            return $records;
        }

        $records = [];
        foreach ($this->selected as $id) {
            $record = $this->dataProvider->find($class, $id);
            if (null !== $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * The current query without pagination, to enumerate every matching record
     * for a select-all bulk action.
     */
    private function allMatchingQuery(): DataQuery
    {
        return new DataQuery(
            search: $this->search,
            searchableFields: $this->searchableFields(),
            sortField: $this->getActiveSortField(),
            sortDirection: $this->getActiveSortDirection(),
            offset: 0,
            limit: max(1, $this->getTotalCount()),
        );
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
        $context = new ActionContext($this->pathPrefix, $this->resource, '');

        $views = [];
        foreach ($actions as $action) {
            if ($action->isVisible()) {
                $views[] = $action->toStandaloneView($context);
            }
        }

        return $views;
    }

    private function query(): DataQuery
    {
        return new DataQuery(
            search: $this->search,
            searchableFields: $this->searchableFields(),
            sortField: $this->getActiveSortField(),
            sortDirection: $this->getActiveSortDirection(),
            offset: (max(1, $this->page) - 1) * $this->perPage,
            limit: $this->perPage,
        );
    }

    /**
     * @return list<string>
     */
    private function searchableFields(): array
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
