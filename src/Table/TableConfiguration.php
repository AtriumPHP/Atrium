<?php

declare(strict_types=1);

namespace Atrium\Table;

use Atrium\Action\Action;
use Atrium\Action\ActionContract;
use Atrium\Table\Action\CreateAction;
use Atrium\Table\Action\EditAction;

/**
 * Fluent description of a resource's list table: its columns plus its record,
 * header and bulk actions. The table equivalent of {@see \Atrium\Form\Schema} for
 * forms — a resource configures it in {@see \Atrium\Resource\AdminResource::table()}
 * and the {@see \Atrium\Twig\Components\DataTable} component reads it. Future
 * table-level options (default sort, page size, filters, empty state) hang here.
 *
 * A fresh instance already carries the framework defaults — an {@see EditAction}
 * record action and a {@see CreateAction} header action — so a resource that only
 * sets `columns()` keeps them, and overriding `table()` does not silently drop
 * them. Pass an empty list to a setter to remove a default.
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
    private array $headerActions;

    /** @var list<Action> */
    private array $bulkActions = [];

    public function __construct()
    {
        $this->recordActions = [EditAction::make()];
        $this->headerActions = [CreateAction::make()];
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
     * Table-level actions shown above the list. Defaults to a "New" button.
     *
     * @param list<Action> $actions
     */
    public function headerActions(array $actions): static
    {
        $this->headerActions = array_values($actions);

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
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
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
    public function getHeaderActions(): array
    {
        return $this->headerActions;
    }

    /**
     * @return list<Action>
     */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }
}
