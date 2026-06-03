# The View (read-only) screen

> A read-only detail screen for one record at `/{resource}/{id}`. Declare what it
> shows with `view()` — a schema of **entry** components — or let it fall back to
> your form rendered read-only.

## When to use

Reach for a View screen when users need to **see** a record without editing it — an
order summary, a user profile, an audit entry — or when view-only roles shouldn't
land in a form. It is **opt-in**: a resource has no View screen until you register
one, so existing resources are unchanged.

The View screen is a **static render** — no form state, no validation, no
round-trips. Its content is a tree of read-only **entries** (the display-side
siblings of form fields) that resolve their value from the record and format it for
display.

## Enabling it

Register a `'view'` page on the resource. That single line turns on the screen, its
route, and an Edit link in the header:

```php
use Atrium\Page\ViewPage;

public static function pages(): array
{
    return [...parent::pages(), 'view' => ViewPage::class];
}
```

The record is now viewable at the **bare** record URL `/{resource}/{id}` (the
editable form stays at `/{resource}/{id}/edit`). Access is gated by the resource's
[`canView($record)`](../resources/authorization.md) ability; an out-of-scope id is
a 404, exactly as in the list.

## Declaring the content — `view()`

`view(Schema)` is a peer of `table()` / `form()`: data-shaped configuration the
resource owns. Fill it with **entries** and the same layout containers
(`Section`, `Grid`, `Fieldset`, `Tabs`, `Flex`) you use in a form:

```php
use Atrium\Form\Schema;
use Atrium\Layout\Section;
use Atrium\View\TextEntry;

public function view(Schema $schema): Schema
{
    return $schema->components([
        Section::make('Details')->columns(2)->schema([
            TextEntry::make('name')->weight('semibold')->columnSpanFull(),
            TextEntry::make('sku')->label('SKU')->badge(),
            TextEntry::make('price')->money('USD', divideBy: 100),
            TextEntry::make('status')->badge()
                ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            TextEntry::make('createdAt')->label('Added')->dateTime('M j, Y'),
        ]),
    ]);
}
```

### The fallback — a free view of your form

If a resource registers a `'view'` page but defines **no `view()`**, the screen
renders the resource's **`form()` fields read-only** (each field's value as static
text). So a basic detail screen costs one line (`'view' => ViewPage::class`); add
`view()` only when you want a tailored layout or richer formatting.

## Entries

An `Entry` (in `Atrium\View`) is a read-only leaf that reads **state** from the
record by name — dot-notation works for relations and JSON:
`TextEntry::make('author.name')`. Every entry, whatever its type, shares this
configuration surface (all closures receive the loaded record):

| Method | Description |
| --- | --- |
| `make(string $name): static` | Create the entry for a record attribute. |
| `label(string): static` | Override the humanised label. |
| `hiddenLabel()` / `inlineLabel()` | Render value-only, or label beside the value. |
| `columnSpan(int\|string)` / `columnSpanFull()` | Grid placement (`'full'` supported). |
| `align(string)` | `start` \| `center` \| `end` \| `right`. |
| `state(mixed)` | Set an explicit state instead of reading the record. |
| `getStateUsing(Closure)` | Compute state: `fn ($record) => …`. |
| `formatStateUsing(Closure)` | Transform the resolved state: `fn ($state, $record) => …`. |
| `default(mixed)` | Value when the record's is `null`. |
| `placeholder(string\|Closure)` | Text shown when the value is empty. |
| `visible(bool\|Closure)` / `hidden(...)` | Show/hide per record. |
| `tooltip()` / `helperText()` / `hint(...)` | Annotations (hover, sub-line, label-row). |
| `icon(string\|Closure)` / `iconPosition('before'\|'after')` | A leading/trailing icon. |
| `url(string\|Closure)` / `openUrlInNewTab()` | Render the value as a link. |
| `extraAttributes(array)` | Extra wrapper attributes (keys must be trusted). |

### `TextEntry`

The workhorse entry — text with formatting:

| Method | Description |
| --- | --- |
| `badge()` / `color(string\|Closure)` | Pill style; semantic colour (`gray`/`info`/`success`/`warning`/`danger`/`primary`). |
| `money(string $currency, int $divideBy = 1)` | Currency formatting (e.g. cents → `$12.34`). |
| `dateTime(format)` / `date(format)` / `since()` | Date/time, or a relative "3 days ago". |
| `numeric(int $decimals = 0)` | Thousands-separated number. |
| `limit(int)` / `words(int)` / `lineClamp(int)` | Truncate by characters / words / lines. |
| `prefix(string)` / `suffix(string)` | Affix text around the value. |
| `html()` | Render the value as trusted HTML (otherwise escaped). |
| `copyable(bool, ?string $message)` | Mark the value copyable. |
| `listWithLineBreaks()` / `bulleted()` / `separator(string)` | Render an array (or split string) as a list. |
| `size('sm'\|'base'\|'lg'\|'xl')` / `weight(...)` / `fontFamily('mono')` | Typography. |

`badge()` / `color()` use the same palette as a table [`Column`](../tables/columns.md)
badge, so the two read identically across the panel.

### Other entry types

Beyond text, the entry family covers the common display shapes. Each reads state
from the record exactly like `TextEntry` (by name, dotted path, `getStateUsing()`),
shares the full base surface above, and shows its `placeholder()` when empty.

#### `IconEntry`

Renders the value **as an icon** — ideal for a status or a boolean flag.

| Method | Description |
| --- | --- |
| `icon(string\|Closure)` | The icon to show (record-aware): `fn ($state) => …`. |
| `boolean(string $trueIcon = 'check', string $falseIcon = 'x')` | Map a truthy/falsy state to a tick, coloured green/red by default. |
| `color(string\|Closure)` | Override the icon colour (semantic palette). |
| `size('sm'\|'md'\|'lg'\|'xl')` | Icon size. |

```php
IconEntry::make('verified')->boolean();
IconEntry::make('status')
    ->icon(fn (string $s) => $s === 'active' ? 'check' : 'minus')
    ->color(fn (string $s) => $s === 'active' ? 'success' : 'gray');
```

#### `ImageEntry`

Renders the value (an image URL) **as an image or avatar**.

| Method | Description |
| --- | --- |
| `circular()` / `square()` | Round the image or keep it square (default). |
| `imageSize(int)` / `imageWidth(int)` / `imageHeight(int)` | Dimensions in pixels. |
| `defaultImageUrl(string)` | Fallback shown when the record has no URL. |

```php
ImageEntry::make('avatar')->circular()->imageSize(48)
    ->defaultImageUrl('/img/avatar-fallback.png');
```

Only `http(s)`, root-relative and protocol-relative sources are rendered; any
other scheme (e.g. `javascript:`, `data:`) is dropped.

#### `ColorEntry`

Renders the value (a CSS colour) **as a swatch** next to its textual value.

| Method | Description |
| --- | --- |
| `copyable(bool $copyable = true, ?string $message = null)` | Mark the value copyable. |

Accepts a `#hex`, `rgb()/rgba()/hsl()/hsla()` or a CSS named colour; anything else
is treated as empty (so a stored value can never break out of the inline style).

#### `KeyValueEntry`

Renders a one-dimensional array / JSON map **as a key→value table** — a metadata
bag, a settings hash, a decoded JSON column.

| Method | Description |
| --- | --- |
| `keyLabel(string)` / `valueLabel(string)` | Column headers (default `Key` / `Value`). |

```php
KeyValueEntry::make('meta')->keyLabel('Attribute')->valueLabel('Value');
```

#### `CodeEntry`

Renders the value **as a monospace, escaped code block** (a snippet, a JSON/SQL
payload). An array state is shown as pretty-printed JSON.

| Method | Description |
| --- | --- |
| `language(string)` | A language label shown above the block. |
| `copyable(bool $copyable = true, ?string $message = null)` | Mark the value copyable. |

```php
CodeEntry::make('payload')->language('json')->columnSpanFull();
```

#### `RepeatableEntry`

Repeats a **nested schema** once per item of a relation or array attribute — an
order's line items, a record's addresses, a list of JSON rows. Each item is bound
as the **record** for the nested entries, so dotted paths and the full entry
vocabulary work inside the block.

| Method | Description |
| --- | --- |
| `schema(array $entries)` | The entries rendered for each item. |
| `columns(int\|array)` | Column grid **within** each item's block. |
| `grid(int\|array)` | Lay the blocks out in N columns (a gallery); default one per row. |
| `contained(bool $contained = true)` | Wrap each block in a card (border + padding). On by default. |

```php
RepeatableEntry::make('items')->columns(3)->schema([
    TextEntry::make('name')->weight('semibold'),
    TextEntry::make('qty')->label('Qty'),
    TextEntry::make('price')->money('USD', divideBy: 100),
]);
```

The state must be a list — a Doctrine collection, an array of entities, or an array
of maps (each map is treated as a record, so a nested `TextEntry::make('name')`
reads its `name` key). It is the one recursive entry: nested layout containers, and
even nested repeatables, compose naturally.

## The View page — presentation

`ViewPage` (a peer of `ListPage`/`CreatePage`/`EditPage`) owns the screen's
presentation; subclass it to customise:

| Method | Description |
| --- | --- |
| `getHeading(PageContext): string` | The `<h1>` (default `View {singular}`). |
| `getSubheading(PageContext): ?string` | Optional sub-line. |
| `getHeaderActions(PageContext): array` | Header buttons (default: **Edit** + **Delete**). |
| `headerWidgets(ListWidgetsConfiguration)` / `footerWidgets(...)` | Record-scoped widget bands (see below). |

The content (the entries) comes from the resource's `view()`, not the page — the
page composes the chrome around it.

### Header actions — Edit and Delete

The default header carries an **Edit** link (to `/{resource}/{id}/edit`) and a
**Delete** button. Even though the View screen is a static render, **Delete runs
server-side** through the shared confirm → delete plumbing (a server-driven
confirmation, no client JS) and redirects to the list once the record is gone.
Each action is gated by its own ability (`edit` / `delete`) and auto-hides when
denied. Override `getHeaderActions()` to add, remove or reorder any
`Action`/`ActionGroup`.

### Widget bands

A `ViewPage` can render widget bands above and below the entries —
`headerWidgets()` / `footerWidgets()`, composed exactly like a dashboard or the
[list bands](../tables/list-widgets.md). Each slot receives the **record id** in
its context, so a record-scoped widget (related rows, an activity timeline) can
fetch its own data:

```php
use Atrium\Layout\Grid;
use Atrium\Page\ViewPage;
use Atrium\Page\ListWidgetsConfiguration;
use Atrium\Widget\WidgetSlot;

final class ViewOrder extends ViewPage
{
    public function headerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config->schema([
            Grid::make(3)->schema([WidgetSlot::make(OrderTotalsWidget::class)]),
        ]);
    }
}
```

## Reaching the View screen — rows & actions

When a resource has a View screen, its **list rows link to it** by default: a row
click opens the record's View page (via a stretched overlay link — the row's
buttons stay independently clickable). A **`ViewAction`** (the bare-record link,
gated by `view`) is also available to add to row or header actions explicitly.

The row-click target is configurable on the table — `TableConfiguration::recordUrl()`:

| Argument | Row click goes to |
| --- | --- |
| `'view'` (default) | The View screen — auto-suppressed when there is no View page or the user can't `view` the record. |
| `'edit'` | The edit screen (same auto-suppression with `edit`). |
| `fn (object $record): ?string` | A custom URL (return `null` to skip a row). |
| `null` | Rows are not clickable. |

```php
public function table(TableConfiguration $table): TableConfiguration
{
    return $table
        ->columns([/* … */])
        ->recordUrl('view'); // the default; pass 'edit', a closure, or null
}
```

## See also

- [Pages overview](overview.md) — the list/create/edit pages and the page-owned model
- [Authorization](../resources/authorization.md) — the `view`/`canView` ability
- [Forms](../forms/overview.md) — the layout containers entries reuse
- [Columns](../tables/columns.md) — the shared badge/colour/format vocabulary
- [Table configuration](../tables/table-configuration.md) — `recordUrl()`, the row-click target
- [List widgets](../tables/list-widgets.md) — the widget-band mechanism the View bands reuse
