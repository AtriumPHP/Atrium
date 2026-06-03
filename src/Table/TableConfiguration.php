<?php

declare(strict_types=1);

namespace Atrium\Table;

use Atrium\Action\Action;
use Atrium\Action\ActionContract;
use Atrium\Table\Action\EditAction;
use Atrium\Table\Filter\Filter;

/**
 * Fluent description of a resource's list table: its columns plus its record and
 * bulk actions. The table equivalent of {@see \Atrium\Form\Schema} for forms — a
 * resource configures it in {@see \Atrium\Resource\AdminResource::table()} and the
 * {@see \Atrium\Twig\Components\DataTable} component reads it. Future table-level
 * options (default sort, page size, filters, empty state) hang here.
 *
 * A fresh instance already carries an {@see EditAction} record action, so a
 * resource that only sets `columns()` keeps it; pass `[]` to `recordActions()` to
 * remove it. **Header actions** (the "New" button etc.) live on the page —
 * {@see \Atrium\Page\ListPage::getHeaderActions()}, not here.
 *
 * Part of the public API contract (PRD §9) — treat changes as BC-relevant.
 */
final class TableConfiguration
{
    /** @var list<Column> */
    private array $columns = [];

    /** @var list<ActionContract> */
    private array $recordActions;

    /** @var list<Action> */
    private array $bulkActions = [];

    /** @var list<Filter> */
    private array $filters = [];

    private ?string $defaultSortField = null;

    private string $defaultSortDirection = 'asc';

    private int $perPage = 10;

    /** @var list<int> */
    private array $perPageOptions = [];

    private ?string $emptyHeading = null;

    private ?string $emptyDescription = null;

    private ?string $emptyIcon = null;

    public function __construct()
    {
        $this->recordActions = [EditAction::make()];
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param list<Column> $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = array_values($columns);

        return $this;
    }

    /**
     * Per-row actions (and/or {@see \Atrium\Action\ActionGroup}s). Defaults to an
     * Edit link; pass `[]` to hide the actions column.
     *
     * @param list<ActionContract> $actions
     */
    public function recordActions(array $actions): static
    {
        $this->recordActions = array_values($actions);

        return $this;
    }

    /**
     * Actions run against the selection. A non-empty list enables row selection;
     * the default is none.
     *
     * @param list<Action> $actions
     */
    public function bulkActions(array $actions): static
    {
        $this->bulkActions = array_values($actions);

        return $this;
    }

    /**
     * Sort the table by this field on first load, until the user picks another.
     * The field need not be a `sortable()` column — it is trusted developer
     * configuration. Direction is `asc` (default) or `desc`.
     */
    public function defaultSort(string $field, string $direction = 'asc'): static
    {
        $this->defaultSortField = $field;
        $this->defaultSortDirection = 'desc' === strtolower($direction) ? 'desc' : 'asc';

        return $this;
    }

    /**
     * Filters shown in the table's filter bar, narrowing the query as the user
     * selects values.
     *
     * @param list<Filter> $filters
     */
    public function filters(array $filters): static
    {
        $this->filters = array_values($filters);

        return $this;
    }

    /**
     * Set the page size, and optionally the choices offered in a per-page
     * selector. Passing options renders the selector (the current `$perPage` is
     * added to the choices if missing).
     *
     * @param list<int> $perPageOptions
     */
    public function paginated(int $perPage, array $perPageOptions = []): static
    {
        $this->perPage = max(1, $perPage);

        $options = $perPageOptions;
        if ([] !== $options && !\in_array($this->perPage, $options, true)) {
            $options[] = $this->perPage;
        }
        sort($options);
        $this->perPageOptions = $options;

        return $this;
    }

    /**
     * Customise the message shown when the table has no rows (an icon, a heading
     * and an optional description), instead of the bare "No … found." default.
     */
    public function emptyState(string $heading, ?string $description = null, ?string $icon = null): static
    {
        $this->emptyHeading = $heading;
        $this->emptyDescription = $description;
        $this->emptyIcon = $icon;

        return $this;
    }

    /**
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getEmptyHeading(): ?string
    {
        return $this->emptyHeading;
    }

    public function getEmptyDescription(): ?string
    {
        return $this->emptyDescription;
    }

    public function getEmptyIcon(): ?string
    {
        return $this->emptyIcon;
    }

    public function getDefaultSortField(): ?string
    {
        return $this->defaultSortField;
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @return list<int>
     */
    public function getPerPageOptions(): array
    {
        return $this->perPageOptions;
    }

    /**
     * @return list<ActionContract>
     */
    public function getRecordActions(): array
    {
        return $this->recordActions;
    }

    /**
     * @return list<Action>
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * @return list<Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
