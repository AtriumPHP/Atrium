# Table configuration

> The `table()` method, where you assemble a resource's list screen — columns,
> sorting, pagination, filters, actions and the empty state — on one builder.

## When to use

Every resource with a list screen defines `table()`. You receive a
`TableConfiguration` already carrying the framework defaults (an **Edit** record
action and a **New** header action) and return it after setting what you need. It
is the single place the whole list is described.

```php
use Atrium\Table\Column;
use Atrium\Table\Filter\SelectFilter;
use Atrium\Table\TableConfiguration;

public function table(TableConfiguration $table): TableConfiguration
{
    return $table
        ->columns([
            Column::make('title')->sortable()->searchable(),
            Column::make('author.name')->label('Author')->sortable(),
            Column::make('status')->badge(),
        ])
        ->defaultSort('title')
        ->paginated(25, [10, 25, 50])
        ->filters([
            SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
        ])
        ->emptyState('No articles yet', 'Create your first article to see it here.', 'document');
}
```

## What you can set

| Method | Description |
| --- | --- |
| `columns(array $columns): static` | The [columns](columns.md) to show. |
| `defaultSort(string $field, string $direction = 'asc'): static` | Initial sort when the user hasn't chosen one. The field need not be a sortable column. |
| `paginated(int $perPage, array $perPageOptions = []): static` | Default page size, and the sizes offered in the per-page selector. |
| `filters(array $filters): static` | The [filters](filters.md) shown above the table. |
| `recordActions(array $actions): static` | Per-row [actions](actions.md) (default: `EditAction`). |
| `headerActions(array $actions): static` | Actions above the table (default: `CreateAction` — the "New" button). |
| `bulkActions(array $actions): static` | Actions run against the selection. Adding any enables row selection. |
| `emptyState(string $heading, ?string $description = null, ?string $icon = null): static` | The message shown when the table has no rows. |

## Defaults and how to remove them

A fresh `TableConfiguration` already includes the Edit and New actions, so the
inline example above keeps both without mentioning them. To **remove** a default,
pass an empty list to its setter:

```php
$table->headerActions([]);   // no "New" button (e.g. a read-only resource)
$table->recordActions([]);   // no per-row actions
```

To add to them, list the defaults alongside your own (the built-ins are plain
classes you can re-add — see [Actions](actions.md)).

## Search, sort and pagination behaviour

These come from the columns and the config, no extra wiring:

- **Search** is a single debounced box that matches across every
  `->searchable()` column (relation columns included). It is bound to the URL, so
  a searched view is bookmarkable.
- **Sort** toggles on any `->sortable()` column header (asc → desc). `defaultSort`
  sets the starting order.
- **Pagination** uses `paginated()`; if you never call it, a sensible default page
  size applies. The page is always clamped into range, so narrowing the results
  (a filter or search) never strands the user on an empty page.

## Empty state

When the query returns no rows — whether the table is genuinely empty or a
filter/search excluded everything — Atrium shows the empty state: the optional
icon, the heading, then the optional description. If you don't call `emptyState()`,
it falls back to a generated *"No \<label\> found."*. See
[Configurable empty state](#empty-state) above for the signature.

## See also

- [Columns](columns.md) — what each cell shows, including relation columns
- [Filters](filters.md) — `SelectFilter`, `TernaryFilter`
- [Actions](actions.md) — record, header and bulk actions in the table
- [Query scoping](../data/query-scoping.md) — restrict which rows appear at all
