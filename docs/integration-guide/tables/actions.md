# Table actions

> Place [actions](../actions/overview.md) in three spots on a list table — per
> row, in the header, and over a selection — from the table configuration.

## When to use

A table has three action slots, each set on the
[`TableConfiguration`](table-configuration.md):

- **Record actions** — one set per row (Edit, Delete, a custom toggle…).
- **Header actions** — buttons above the table (New, Import…), with no record.
- **Bulk actions** — run against the selected rows; adding any turns on row
  selection (including "select all matching the query" across pages).

The `Action` builder itself is documented in [Actions](../actions/overview.md);
this page is about where they go.

```php
use Atrium\Action\Action;
use Atrium\Action\ActionGroup;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Table\Action\BulkDeleteAction;
use Atrium\Table\Action\DeleteAction;
use Atrium\Table\Action\EditAction;
use Atrium\Table\TableConfiguration;

public function table(TableConfiguration $table): TableConfiguration
{
    return $table
        ->columns([/* ... */])
        ->recordActions([
            EditAction::make(),
            DeleteAction::make(),
            ActionGroup::make([
                Action::make('feature')->label('Toggle featured')->icon('star')
                    ->action(function (object $record, DataWriterInterface $writer): void {
                        $record->featured = !$record->featured;
                        $writer->update($record);
                    }),
            ])->label('More'),
        ])
        ->bulkActions([
            BulkDeleteAction::make(),
            Action::make('feature')->label('Feature selected')->icon('star')
                ->action(function (array $records, DataWriterInterface $writer): void {
                    foreach ($records as $record) {
                        $record->featured = true;
                        $writer->update($record);
                    }
                }),
        ]);
    // headerActions default to [CreateAction::make()] — the "New" button.
}
```

## Record actions

Per-row actions. A record action's handler receives the row's record and the data
writer. Group rarely-used ones into an [`ActionGroup`](../actions/overview.md#grouping--actiongroup)
("More" dropdown) to keep the row uncluttered. Per-record `visible()` closures and
`authorize()` decide which appear on which row.

The default is `[EditAction::make()]`; pass `recordActions([])` for a read-only
table.

## Header actions

Buttons above the table, with no record in scope — typically links. The default is
`[CreateAction::make()]` (the "New" button). Because there's no record, a header
action's `visible()` must be a plain bool (a record-bound closure can't be
evaluated and fails closed). Remove the New button with `headerActions([])`.

## Bulk actions & selection

Adding **any** bulk action enables row selection. Users can select rows on the
page, select the whole page, or **select every record matching the current query**
(across all pages) — and a bulk action then runs against that whole set. A bulk
handler receives the list of records and the writer.

Bulk actions respect authorization per record: a `BulkDeleteAction` acts only on
the rows the user may delete, and a panel-level ability (like `create`) is checked
before the action runs at all.

```php
->bulkActions([
    BulkDeleteAction::make(),
]);
```

## Lifecycle

Every action runs inside a transaction, bracketed by the resource's
[action lifecycle hooks](../resources/lifecycle-hooks.md)
(`beforeAction`/`afterAction`, `beforeBulkAction`/`afterBulkAction`), with
`beforeDelete`/`afterDelete` for deletes. A throwing handler rolls the action back.

## See also

- [Actions](../actions/overview.md) — the `Action` / `ActionGroup` builders and built-ins
- [Table configuration](table-configuration.md) — the `recordActions` / `headerActions` / `bulkActions` setters
- [Authorization](../resources/authorization.md) — gating who sees and runs each action
