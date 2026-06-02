# Layout

> Arrange fields into responsive grids, grouped sections and fieldsets — the
> structure of a form, configured in PHP.

## When to use

By default a form is a single column of fields. Layout components let you shape
that: put fields side by side in a **grid**, group related fields under a
**section** heading, or box them in a **fieldset**. They nest freely, so a complex
form is just a tree of layout components with fields at the leaves.

To use layout, return the tree from `form()` with `components()` (which accepts a
mix of layout components and fields) instead of the flat `fields()`:

```php
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Layout\Grid;
use Atrium\Layout\Section;

public function form(Schema $schema): Schema
{
    return $schema->components([
        Section::make('Details')->schema([
            Grid::make(2)->schema([
                TextField::make('firstName'),
                TextField::make('lastName'),
            ]),
            TextField::make('email')->email()->columnSpanFull(),
        ]),
    ]);
}
```

## The grid model

Layout containers lay their children on a **column grid**. A container's
`columns(int)` sets how many columns it spans its children across (responsive — it
collapses to one column on small screens). Each child controls how many columns it
occupies with `columnSpan(int)` or `columnSpanFull()`.

```php
Grid::make(3)->schema([
    TextField::make('street')->columnSpanFull(), // full width
    TextField::make('city'),                     // 1 of 3
    TextField::make('state'),                    // 1 of 3
    TextField::make('zip'),                      // 1 of 3
]);
```

`columnSpan` and `columnSpanFull` are available on **fields and containers alike**,
so any node can claim more width within its parent grid.

## Components

### `Grid`

A bare responsive grid — no heading, no border. The building block for placing
fields side by side.

```php
Grid::make(2)->schema([ /* ... */ ]);        // 2 columns
Grid::make()->schema([ /* ... */ ]);          // defaults to 2
```

### `Section`

A titled group with an optional description, ideal for breaking a long form into
labelled chunks. Can be made collapsible.

| Method | Description |
| --- | --- |
| `make(?string $heading): self` | Create with an optional heading. |
| `description(?string): self` | Sub-heading text. |
| `columns(int\|array): self` | Column grid for its children. |
| `collapsible(bool = true): self` | Allow the user to collapse it. |
| `collapsed(bool = true): self` | Start collapsed (implies collapsible). |
| `compact(bool = true): self` | Tighter padding. |

```php
Section::make('SEO')
    ->description('Optional metadata for search engines.')
    ->columns(2)
    ->collapsible()
    ->schema([
        TextField::make('metaTitle'),
        TextField::make('metaDescription'),
    ]);
```

### `Fieldset`

A bordered group with a label — a lighter visual grouping than a section, rendered
as a real `<fieldset>`.

| Method | Description |
| --- | --- |
| `make(string $label): self` | Create with a legend label. |
| `contained(bool = true): self` | Draw the surrounding border/box. |
| `columns(int\|array): self` | Column grid for its children. |

### `Flex`

A flex row: children sit in a line and wrap, rather than on a fixed grid. Use it
for a toolbar-like row of inputs of differing widths. `from(string $breakpoint)`
sets the screen size at which it becomes a row (below it, they stack).

```php
Flex::make()->from('md')->schema([
    TextField::make('search')->grow(),   // takes the remaining space
    SelectField::make('scope'),
]);
```

`grow()` (on fields and containers) lets a child expand to fill leftover space in
a `Flex` row.

## Conditional layout

Layout containers support the same [visibility](reactivity.md) rules as fields:
`visible()` / `hidden()` with a bool or a closure of the form state, and
`visibleOn()` / `hiddenOn()` for create-vs-edit. When a container is hidden, its
whole subtree is removed from the form — not rendered, not validated.

```php
Section::make('Publishing')
    ->visible(fn (Get $get): bool => 'published' === $get('status'))
    ->schema([ /* ... */ ]);
```

## See also

- [Tabs & wizards](tabs-and-wizards.md) — multi-panel and multi-step containers
- [Reactive fields](reactivity.md) — conditional visibility with `Get`
- [Fields](fields.md) — the inputs that go inside these containers
- [Content](content.md) — adding text/images between fields
