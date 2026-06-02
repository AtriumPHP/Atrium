<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataQuery;
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

    /** @var list<Column>|null */
    private ?array $columns = null;

    private ?int $totalCount = null;

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly PropertyAccessorInterface $accessor,
    ) {
    }

    public function mount(string $resource, int $perPage = 10): void
    {
        $this->resource = $resource;
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
     * Pre-rendered rows: each row is an ordered list of formatted cell strings.
     *
     * @return list<array{cells: list<string>}>
     */
    public function getRows(): array
    {
        $columns = $this->getColumns();
        $rows = [];

        foreach ($this->dataProvider->fetch($this->entityClass(), $this->query()) as $record) {
            $cells = [];
            foreach ($columns as $column) {
                $cells[] = $column->renderValue($record, $this->accessor);
            }
            $rows[] = ['cells' => $cells];
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
