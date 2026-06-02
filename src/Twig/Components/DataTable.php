<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\Action\ActionContract;
use Atrium\Action\Concern\InteractsWithActions;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Table\Column;
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

    #[LiveProp]
    public int $perPage = 10;

    /**
     * Panel path prefix, kept in state so record-action URLs survive re-renders.
     */
    #[LiveProp]
    public string $pathPrefix = '';

    /** @var list<Column>|null */
    private ?array $columns = null;

    /** @var list<ActionContract>|null */
    private ?array $recordActions = null;

    private ?int $totalCount = null;

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly DataWriterInterface $writer,
        private readonly PropertyAccessorInterface $accessor,
    ) {
    }

    public function mount(string $resource, string $pathPrefix = '', int $perPage = 10): void
    {
        $this->resource = $resource;
        $this->pathPrefix = $pathPrefix;
        $this->perPage = $perPage;
    }

    #[LiveAction]
    public function sort(#[LiveArg] string $field): void
    {
        if (!$this->isSortable($field)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = DataQuery::SORT_ASC === $this->sortDirection
                ? DataQuery::SORT_DESC
                : DataQuery::SORT_ASC;
        } else {
            $this->sortField = $field;
            $this->sortDirection = DataQuery::SORT_ASC;
        }

        $this->page = 1;
    }

    #[LiveAction]
    public function gotoPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, min($page, $this->getPageCount()));
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    /**
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return $this->columns ??= array_values($this->resource()->columns());
    }

    /**
     * The resource's record actions (per-row actions and action groups).
     *
     * @return list<ActionContract>
     */
    public function getRecordActions(): array
    {
        return $this->recordActions ??= array_values($this->resource()->recordActions());
    }

    public function hasRecordActions(): bool
    {
        return [] !== $this->getRecordActions();
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
        $this->page = min($this->page, $this->getPageCount());
    }

    /**
     * Pre-rendered rows: formatted cell strings plus the record id and the
     * per-record actions resolved (for visibility, URL, style) into render-ready
     * descriptors — a plain action, or a `kind: 'group'` dropdown of them.
     *
     * @return list<array{id: ?string, cells: list<string>, actions: list<array<string, mixed>>}>
     */
    public function getRows(): array
    {
        $columns = $this->getColumns();
        $items = $this->getRecordActions();
        $rows = [];

        foreach ($this->dataProvider->fetch($this->entityClass(), $this->query()) as $record) {
            $cells = [];
            foreach ($columns as $column) {
                $cells[] = $column->renderValue($record, $this->accessor);
            }

            $id = $this->recordId($record);
            $context = new ActionContext($this->pathPrefix, $this->resource, $id ?? '');

            $rowActions = [];
            foreach ($items as $item) {
                if ($item->isVisibleFor($record)) {
                    $rowActions[] = $item->toView($record, $context, $id);
                }
            }

            $rows[] = ['id' => $id, 'cells' => $cells, 'actions' => $rowActions];
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

    private function query(): DataQuery
    {
        $sortField = null !== $this->sortField && $this->isSortable($this->sortField)
            ? $this->sortField
            : null;

        return new DataQuery(
            search: $this->search,
            searchableFields: $this->searchableFields(),
            sortField: $sortField,
            sortDirection: $this->sortDirection,
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
