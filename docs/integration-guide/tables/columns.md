# Columns

> Describe one cell of the list table — what to read, how to format it, and how it
> behaves (sort, search, alignment, badges).

## When to use

Every column in a resource's table is a `Column`. You add them on the
`TableConfiguration` returned from `table()`:

```php
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;

public function table(TableConfiguration $table): TableConfiguration
{
    return $table->columns([
        Column::make('title')->sortable()->searchable(),
        Column::make('status')->badge()->color(fn (string $v): string => 'published' === $v ? 'green' : 'amber'),
        Column::make('featured')->boolean()->alignCenter(),
        Column::make('author.name')->label('Author')->sortable()->searchable(), // relation column
        Column::make('publishedAt')->label('Published')->sortable()->alignRight(),
    ]);
}
```

## Relation columns

A **dotted name** reads through a relation: `Column::make('author.name')` displays
`record.author.name`. There is nothing else to configure — it sorts, searches and
filters like any other column. Arbitrary depth works too
(`Column::make('author.company.name')`).

```php
Column::make('author.name')->sortable()->searchable(),
```

How it behaves:

- **Display** — the value is read by path; if any link in the chain is `null`
  (e.g. an article with no author), the cell is simply empty.
- **Sort / search / filter** — the Doctrine adapter adds the necessary
  `LEFT JOIN`s automatically (so rows with a null relation are kept), binding all
  values as parameters. The in-memory array provider traverses the path directly.
- **Label** — the default humanises the whole path (`author.name` → "Author
  name"); pass `->label('Author')` for something tighter.

Relation columns assume a **to-one** relation (you are showing a single related
value). To display something derived from a *to-many* relation, compute it with
[`formatStateUsing()`](#custom-formatting) from the record and leave the column
non-sortable/non-searchable.

## Custom formatting

`formatStateUsing(callable)` overrides how a value is rendered; the callback gets
the raw value and the whole record:

```php
Column::make('priceCents')
    ->label('Price')
    ->formatStateUsing(fn (int $cents): string => '$'.number_format($cents / 100, 2));

Column::make('author.name')
    ->formatStateUsing(fn (?string $name, object $record): string => $name ?? 'Unattributed');
```

Without a formatter, values are rendered sensibly by type: scalars as-is,
`DateTimeInterface` as `Y-m-d H:i`, `BackedEnum`/`UnitEnum` by value/name, bools as
Yes/No (or an icon with `->boolean()`), arrays joined with commas, null as empty.

## API reference

| Method | Description |
| --- | --- |
| `make(string $name): self` | Create a column. `$name` is a property path — `'title'` or a relation path like `'author.name'`. |
| `label(string $label): self` | Override the generated header label. |
| `sortable(bool = true): self` | Allow click-to-sort on this column (relation paths included). |
| `searchable(bool = true): self` | Include this column in the table search (relation paths included). |
| `visible(bool = true): self` / `hidden(bool = true): self` | Show/hide the column outright (dropped from header, cells, search, sort). |
| `alignment(string): self` / `alignCenter(): self` / `alignRight(): self` | Horizontal alignment (`left`/`center`/`right`). |
| `width(string): self` | Fixed CSS width on the header cell (e.g. `'8rem'`). |
| `boolean(bool = true): self` | Render the value as a check/cross icon. |
| `badge(bool = true): self` | Render the value as a coloured pill. |
| `color(string\|Closure): self` | Badge colour key, or a callback of `(value, record)` returning one. |
| `formatStateUsing(callable): self` | Override rendering; callback receives `(rawValue, record)`. |

## See also

- [Query scoping](../data/query-scoping.md) — which records the table shows
- [Authorization](../resources/authorization.md) — gating actions on rows
