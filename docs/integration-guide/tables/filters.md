# Filters

> Let users narrow the table by a field's value — a dropdown of choices, or a
> yes/no/any toggle — from a filter bar above the list.

## When to use

Add filters when users need to slice the list by a known field: status, category,
a boolean flag. Filters render as controls above the table; selecting one narrows
the results immediately (a server round-trip, like search). They are set on the
[table configuration](table-configuration.md) with `filters()`:

```php
use Atrium\Table\Filter\SelectFilter;
use Atrium\Table\Filter\TernaryFilter;

$table->filters([
    SelectFilter::make('status')->options([
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ]),
    TernaryFilter::make('featured')->labels('Featured', 'Not featured'),
]);
```

A "Reset" control clears all active filters at once, and the page returns to one
whenever a filter changes.

> Filters apply an **equality** condition on a field, bound as a query parameter.
> They narrow what the user can already see; to restrict the underlying rows
> regardless of the UI (multi-tenancy, soft-deletes), use
> [query scoping](../data/query-scoping.md) instead.

## `SelectFilter`

A dropdown that filters by an exact match on a field.

| Method | Description |
| --- | --- |
| `make(string $name): self` | Create the filter; `$name` is also the field, unless overridden. |
| `options(array $options): self` | `value => label` choices. Only known option values are applied. |
| `placeholder(string): self` | The "no filter" prompt (the unfiltered state). |
| `attribute(string $field): self` | Filter a different field than the filter's name. |
| `label(string): self` | Override the control's label. |

```php
SelectFilter::make('kind')
    ->label('Type')
    ->options(['fruit' => 'Fruit', 'tool' => 'Tool'])
    ->placeholder('Any type'),
```

## `TernaryFilter`

A three-state control for a boolean field: **any** (no filter), **true**, or
**false**.

| Method | Description |
| --- | --- |
| `make(string $name): self` | Create the filter. |
| `labels(string $true, string $false): self` | Labels for the true / false choices. |
| `placeholder(string): self` | Label for the "any" (unfiltered) choice. |
| `attribute(string $field): self` | Filter a different field than the name. |
| `label(string): self` | Override the control's label. |

```php
TernaryFilter::make('active')
    ->labels('Active', 'Inactive')
    ->placeholder('All'),
```

## Filtering a different field

By default the filter's `name` is both its identity and the field it filters. Use
`attribute()` when they differ — e.g. a filter named `team` that filters
`teamId`:

```php
SelectFilter::make('team')->attribute('teamId')->options($teams),
```

## See also

- [Table configuration](table-configuration.md) — where filters are registered
- [Query scoping](../data/query-scoping.md) — restrict rows independently of the UI
- [Columns](columns.md) — search and sort, the other ways to find rows
