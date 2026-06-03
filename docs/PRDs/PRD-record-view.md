# PRD — Record View (read-only) pages

> A read-only "View" screen for a single record. The resource declares a
> `view(Schema)` of **entry** components (read-only siblings of form fields,
> reusing the same layout tree); if it declares none, the View screen renders the
> resource's `form()` read-only. Reached at the bare record URL
> `/{resource}/{id}`, gated by the existing `view` ability.

Status: **proposed** · Feature IDs: `VIEW-01`..`VIEW-18`

## 1. Motivation

Atrium ships List, Create and Edit screens, but no first-class way to **show** a
single record read-only. Today an integrator who wants a detail page must either
send users into the editable form (wrong for view-only roles, risky for
accidental edits) or hand-write a controller + template. A read-only record view
is one of the most common admin needs — order details, a user profile, an audit
record — and should be declarative.

The pieces are largely in place: a `Page` descriptor layer that already owns
per-screen presentation; a `Schema` that is a generic tree of `Layout\Component`
nodes; `Content` display components (`Text`/`Image`/`UnorderedList`); the
`Action` subsystem with `EditAction`/`DeleteAction`; and the `view`/`canView`
authorization ability, **already wired** (`AdminResource::can('view', $record)`).
The `Layout\Component` interface docblock even names "infolist entries" as a
planned implementer. This feature adds the missing leaf type (entries), the View
page + route, and the read-only fallback.

## 2. Goals

- `VIEW-01` — A resource exposes a read-only **View screen** for one record at the
  bare URL `/{resource}/{id}` (PathPrefix-relative), distinct from
  `/{resource}/{id}/edit`.
- `VIEW-02` — A resource declares the view's content with **`view(Schema $schema)`**
  (peer to `table()` / `form()`). If it does **not**, the View screen renders the
  resource's **`form()` schema read-only** — a view page is free.
- `VIEW-03` — A read-only **`Entry`** leaf type lives in the **same** schema/layout
  tree as form fields: entries, fields, layout containers (`Section`/`Grid`/
  `Fieldset`/`Tabs`/`Flex`) and `Content` components compose in one `Schema`.
- `VIEW-04`..`VIEW-10` — The entry component family (full parity), each resolving
  state from the record by name/dotted path:
  - `VIEW-04` **`TextEntry`** — the workhorse: `->badge()`, `->color()`,
    `->money()`, `->dateTime()` / `->date()` / `->since()`, `->numeric()`,
    `->limit()` / `->words()`, `->html()`, `->copyable()`, `->icon()`,
    `->placeholder()`, `->state()` / `->getStateUsing()` / `->formatStateUsing()`,
    `->listWithLineBreaks()` / `->bulleted()` / `->separator()`.
  - `VIEW-05` **`IconEntry`** — state→icon (+ color); `->boolean()` true/false ticks.
  - `VIEW-06` **`ImageEntry`** — image/avatar; `->circular()`, sizing, default URL.
  - `VIEW-07` **`ColorEntry`** — a colour swatch, copyable.
  - `VIEW-08` **`KeyValueEntry`** — a 1-D array/JSON rendered as a key→value table.
  - `VIEW-09` **`RepeatableEntry`** — repeats a **nested schema** per item of a
    relation/array attribute.
  - `VIEW-10` **`CodeEntry`** — a monospace / lightly highlighted code block.
- `VIEW-11` — A **`ViewPage`** (peer to `ListPage`/`CreatePage`/`EditPage`) owns the
  view screen's presentation: heading (`"View {singular}"`) and header actions
  (default **Edit** + **Delete**).
- `VIEW-12` — When a resource has a View page, a **`ViewAction`** is available for
  table rows and the **row click defaults to the View screen** (both gated by
  `canView`), matching the least-destructive default.
- `VIEW-13` — Authorization reuses the existing `view`/`canView($record)` ability;
  `PageContext` gains `viewUrl(id)`.
- `VIEW-14` — Docs + playground demo.
- `VIEW-15` — A full, **shared entry configuration surface** (the `Entry` base):
  labelling (`label`/`hiddenLabel`/`inlineLabel`), layout (`columnSpan`/
  `columnStart`/`align`/`extraAttributes`), record-aware `visible`/`hidden`,
  annotation (`tooltip`/`helperText`/`hint`+icon/color), `icon`(+position),
  **link** (`url`/`openUrlInNewTab`), and **inline actions**
  (`prefixActions`/`suffixActions`/`hintActions`) — available on every entry type,
  with all closure options evaluated against the loaded record.
- `VIEW-16` — Optional **header/footer widget bands** on the View screen
  (`ViewPage::headerWidgets()`/`footerWidgets()`), reusing the dashboard/list
  `WidgetLayoutConfiguration` + `WidgetSlot`, with the **record id** in context.
- `VIEW-17` — A **configurable row-click target** (view / edit / custom URL closure
  / off) and `ViewAction` presence, set on the list page — the view default is
  opinionated but overridable.
- `VIEW-18` — The **custom-page escape hatch**: `pages()['view']` may point at a
  custom `Page` that renders its own template, for detail layouts the schema can't
  express.

## 3. Non-goals

- **A reactive/Live-Component view.** The View screen is a **static render** — no
  hydration, no validation, no persistence, no round-trips. (The form is a Live
  Component because it mutates; a view does not.) Server actions in the view's
  header (Edit link, Delete) run through the existing action plumbing, not a
  view-specific Live Component.
- **Inline editing on the view.** No "click to edit" affordances; Edit is a
  navigation to the edit screen.
- **A second schema engine.** Entries are `Layout\Component`s placed in the
  **existing** `Schema` tree and rendered by the **existing** recursive renderer.
  No parallel layout system.
- **View as a navigation entry.** A View screen is reached from a row / the bare
  record URL / an Edit-screen back-link — not the sidebar.
- **A maker for view pages.** Out of scope (the maker is deferred generally).

## 4. Decisions (from research + brainstorm)

The design **follows the researched prior art** deliberately (see the external
research feeding this PRD); the choices below mirror that model, adapted to
Atrium's names and architecture.

| Decision | Choice |
| --- | --- |
| Display model | A dedicated **entry** component family (read-only), **not** disabled form inputs, for presentation-quality output (badges, money/date formatting, computed values, images, relation lists). |
| Tree | **Unified** — entries are leaves in the **same** `Schema`/layout tree as fields (one schema holding both fields and entries), reusing `Section`/`Grid`/`Fieldset`/`Tabs`/`Flex` and `Content`. |
| Schema source | **`view(Schema)` on the resource** (data-shaped, peer to `table()`/`form()`). Absent → render `form()` **read-only** (the freebie fallback). |
| Naming | `view()` + `*Entry` classes (`TextEntry`, `IconEntry`, …) under **`Atrium\View`** — neutral names that parallel the existing `table()`/`form()` API. |
| Presentation | A **`ViewPage`** owns heading + header actions (Edit, Delete); the resource owns the data-shaped `view()`. (Consistent with the page-owned model — see [PRD-page-screens](PRD-page-screens.md), [PRD-list-widgets](PRD-list-widgets.md).) |
| Routing | **Bare** `/{resource}/{id}` = view; `/{resource}/{id}/edit` = edit. Non-destructive, shareable default. |
| Row click | When a View page exists, the **row links to view** and a `ViewAction` is added to rows. Gated by `canView`. |
| Authorization | Reuse the existing `view`/`canView($record)` ability (no new concept). |
| Opt-in | A View screen exists when the resource **registers a `'view'` page** (or defines `view()`); plain resources are unchanged. |

### Why entries, not disabled form inputs

The cheap path — render `form()` with inputs `disabled()` — is the *fallback*, not
the recommended surface. Disabled inputs are poor display UX: a greyed `<select>`
is a bad way to show a status (no badge), money/dates aren't formatted, computed
and relation values can't be shown, and disabled controls are weak for
accessibility and copy. The entry family exists precisely to close that gap, while
the disabled-form fallback keeps a view page free for the trivial case.

## 5. API design

### 5.1 The view schema — on the resource

`view()` is a peer of `table()`/`form()`: data-shaped configuration the resource
owns. It receives and returns the **existing** `Atrium\Form\Schema` (a generic
`Component` tree — `getComponents()` renders it; `getFields()` simply ignores
non-field leaves, so entries coexist with fields):

```php
use Atrium\Form\Schema;
use Atrium\Layout\Section;
use Atrium\View\{TextEntry, IconEntry, ImageEntry};

public function view(Schema $schema): Schema
{
    return $schema->components([
        Section::make('Details')->columns(2)->schema([
            TextEntry::make('name')->weight('semibold'),
            TextEntry::make('status')->badge()
                ->color(fn (string $s) => match ($s) { 'active' => 'success', default => 'gray' }),
            TextEntry::make('price')->money('EUR'),
            TextEntry::make('createdAt')->dateTime(),
            ImageEntry::make('photo')->circular(),
        ]),
    ]);
}
```

> **Naming wrinkle (resolved):** the container is `Atrium\Form\Schema`, reused
> as-is because it is already a generic component tree (a schema shared by both
> forms and views is the natural shape for exactly this dual use). We keep the
> existing class to
> avoid a BC-breaking move now; a future pass may extract `Schema` to a neutral
> namespace. Entries do **not** depend on `Atrium\Form`; they are `Atrium\View`
> leaves implementing `Atrium\Layout\Component`.

**Fallback.** The base `AdminResource::view()` returns the schema unchanged
(empty), and an `@internal` resolver decides what the screen renders:

```php
final public function resolveViewSchema(): Schema   // @internal
{
    $view = $this->view(new Schema());
    return $view->getComponents() === []
        ? $this->form(new Schema())   // fall back to the form, rendered read-only
        : $view;
}
```

When the fallback fires, the renderer draws each `Field` in **read-only mode**
(value as static text where sensible, else a disabled control) — never an active
input. No validation, no hydration.

### 5.2 The entry family — `Atrium\View`

An `Entry` is the read-only sibling of `Field`: a `Layout\Component` leaf that
resolves **state** from the record (by `name`, dot-notation for relations/JSON:
`TextEntry::make('author.name')`) and renders display markup. The base class
carries the **full shared configuration surface** — every entry, whatever its
type, can be relabelled, hidden, placed in the grid, made into a link, annotated,
and have its state and presentation driven by closures. Each concrete entry then
adds its own type-specific vocabulary on top.

```php
namespace Atrium\View;

use Atrium\Action\Action;
use Atrium\Layout\Component;

abstract class Entry implements Component
{
    public static function make(string $name): static { /* … */ }

    // -- Labelling & layout -------------------------------------------------
    public function label(string|\Closure $label): static { /* … */ }
    public function hiddenLabel(bool $hidden = true): static { /* … */ }     // value only, no label
    public function inlineLabel(bool $inline = true): static { /* … */ }     // label beside, not above
    public function columnSpan(int|string $span): static { /* … */ }         // 'full' supported
    public function columnStart(int|string $start): static { /* … */ }
    public function align(string $align): static { /* … */ }                 // start|center|end|right
    public function extraAttributes(array $attributes): static { /* … */ }   // arbitrary wrapper attrs

    // -- State (record-aware closures) -------------------------------------
    public function state(mixed $value): static { /* … */ }                  // explicit static state
    public function getStateUsing(\Closure $resolver): static { /* … */ }    // fn(object $record): mixed
    public function formatStateUsing(\Closure $formatter): static { /* … */ }// fn(mixed $state, object $record): string|Stringable
    public function default(mixed $value): static { /* … */ }                // before state resolves
    public function placeholder(string|\Closure $text): static { /* … */ }   // shown when state is null/empty

    // -- Visibility (record-aware) -----------------------------------------
    public function visible(bool|\Closure $condition = true): static { /* … */ } // fn(object $record): bool
    public function hidden(bool|\Closure $condition = true): static { /* … */ }

    // -- Annotation --------------------------------------------------------
    public function tooltip(string|\Closure $text): static { /* … */ }
    public function helperText(string|\Closure $text): static { /* … */ }    // sub-line under the value
    public function hint(string|\Closure $text): static { /* … */ }          // text aligned to the label row
    public function hintIcon(string $icon): static { /* … */ }
    public function hintColor(string $color): static { /* … */ }
    public function icon(string|\Closure $icon): static { /* … */ }
    public function iconPosition(string $position): static { /* … */ }       // before|after

    // -- As a link ---------------------------------------------------------
    public function url(string|\Closure $url): static { /* … */ }            // fn(object $record): string
    public function openUrlInNewTab(bool $new = true): static { /* … */ }

    // -- Inline actions (reuse the Action subsystem) -----------------------
    /** @param list<Action> $actions */
    public function prefixActions(array $actions): static { /* … */ }
    public function suffixActions(array $actions): static { /* … */ }
    public function hintActions(array $actions): static { /* … */ }

    // Component contract: getChildComponents()/getTemplate()/getColumnSpanClass()/getGrowClass()
}
```

**Closure injection.** Every closure-valued option (`visible`/`hidden`,
`label`, `getStateUsing`, `formatStateUsing`, `color`, `icon`, `url`,
`placeholder`, `tooltip`, …) is evaluated **against the loaded record** at render
time, receiving the resolved `$state` and the `$record` where relevant — so an
entry can show, hide, recolour or relabel itself per record without the integrator
threading the record through. (The `view()` method itself stays record-less, like
`form()`/`table()`; per-entry closures are the record-aware seam.)

Concrete entries (full parity), each `Entry::make('attribute')` — on top of the
shared surface above:

| Entry | Adds |
| --- | --- |
| `TextEntry` | `badge()`, `color()`, `money(currency, divideBy)`, `dateTime(format)` / `date()` / `since()`, `numeric(decimals)`, `limit(n)` / `words(n)` / `lineClamp(n)`, `prefix()` / `suffix()`, `html()` / `markdown()`, `copyable(message, duration)`, `listWithLineBreaks()` / `bulleted()` / `separator(',')`, `size()` / `weight()` / `fontFamily()` |
| `IconEntry` | `icon(state→name)`, `color(state→color)`, `boolean(trueIcon/falseIcon/colors)`, `size()` |
| `ImageEntry` | `disk()`, `visibility()`, `height()` / `width()` / `size()`, `circular()` / `square()`, `defaultImageUrl()`, `stacked()` / `ring()` / `overlap()` / `limit(n)` / `limitedRemainingText()` (galleries) |
| `ColorEntry` | renders the colour as a swatch; `copyable()` |
| `KeyValueEntry` | `keyLabel()` / `valueLabel()` (state must be an array/JSON map) |
| `RepeatableEntry` | `schema([...])` of nested entries, repeated per item; `columns(n)`, `grid(n)`, `contained(false)` |
| `CodeEntry` | `language()`; monospace block |

`TextEntry`'s `badge()`/`color()` reuse the **same semantic colour palette** and
badge styling already in `Content\Text` and `Table\Column` (`gray`/`info`/
`success`/`warning`/`danger`/`primary`) — one visual vocabulary across the panel.
Dotted-path state resolution reuses the relation accessor built for relation
columns (see [PRD relation columns / `Column`](../integration-guide/tables/columns.md)).

### 5.3 The view page — `Atrium\Page\ViewPage`

Owns only presentation, like the other pages:

```php
namespace Atrium\Page;

use Atrium\Action\Action;
use Atrium\Table\Action\{DeleteAction, EditAction};

class ViewPage extends Page
{
    public function getHeading(PageContext $context): string
    {
        return 'View '.$context->singularLabel;
    }

    /** @return list<Action> */
    public function getHeaderActions(PageContext $context): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}
```

Registered like any page:

```php
public static function pages(): array
{
    return [
        'index'  => ListPage::class,
        'create' => CreatePage::class,
        'edit'   => EditPage::class,
        'view'   => ViewPage::class,   // opt in to the View screen
    ];
}
```

On the View screen, `EditAction`'s header button links to `/{resource}/{id}/edit`
(view → edit), and `DeleteAction` runs the existing confirmed delete against the
loaded record, then redirects to the list (the record is gone). Each header action
is gated by its ability (`edit`/`delete`) and auto-hides when denied.

Everything a `ViewPage` can configure:

- **Heading / subheading / title** — `getHeading()` / `getSubheading()` /
  `getTitle()` (record-aware via `PageContext.entityId` + the loaded record); both
  may interpolate record data (e.g. `"Order #{$context->entityId}"`).
- **Header actions** — override `getHeaderActions()` to add, remove or reorder;
  any `Action` or `ActionGroup` (custom server actions, links, an "Edit" dropdown).
  Each carries its own `authorize()`/`visible()`/confirmation.
- **Header & footer widget bands** — optional `headerWidgets()` / `footerWidgets()`
  on the `ViewPage`, reusing the **same** `WidgetLayoutConfiguration` +
  `WidgetSlot` mechanism as the dashboard and the list screen (see
  [PRD-list-widgets](PRD-list-widgets.md)). The widgets receive the
  resource-identity **and the record id** as context, so a "related orders" or
  "activity timeline" widget can scope to this record. Empty by default.
- **Post-delete redirect** — `getRedirectUrl()` (defaults to the list).
- **The schema** — the *content* comes from the resource's `view()` (data-shaped),
  not the page; the page composes the chrome around it.

**Full escape hatch.** Because `pages()['view']` is just a class, an integrator who
needs something the schema can't express points it at a **custom `Page` subclass
that renders its own Twig template** — the same escape valve every screen has. The
`view()` schema covers the common case; a bespoke detail layout is always reachable
without fighting the framework.

### 5.4 Routing + controller

A new bare route, declared **after** create/edit so a literal `/new` still routes
to create (Symfony first-match wins):

```php
// config/routes.php — order: create, edit, view, catch-all
$routes->add('atrium_resource_view', '/admin/{resource}/{id}')
    ->controller([AdminController::class, 'view'])
    ->requirements(['resource' => '[a-z0-9-]+', 'id' => '[^/]+']);
```

`AdminController::view()` mirrors `edit()` — resolve resource, gate
`canAccess() && canView($record)`, scoped `find()` (404 outside scope), resolve the
`'view'` page; **404 if the resource exposes no `'view'` page** (the screen is
opt-in). It renders a new static template with the resolved view schema:

```php
public function view(string $resource, string $id): Response
{
    $resourceObject = $this->requireResource($resource);
    $this->denyUnless($resourceObject->canAccess());

    $page = $resourceObject->resolvePage('view');
    if (null === $page) {
        throw new NotFoundHttpException(/* no view screen for this resource */);
    }

    $record = $this->dataProvider?->find($resourceObject->getEntityClass(), $id, $resourceObject->scopeFilters());
    if (null === $record) { throw new NotFoundHttpException(/* … */); }
    $this->denyUnless($resourceObject->canView($record));

    $context = $this->pageContext($resourceObject, $resource, $id);

    return $this->render('@Atrium/admin/view_page.html.twig', [
        'panel' => $this->panel($resource),
        'resource' => $resourceObject,
        'heading' => $page->getHeading($context),
        'subheading' => $page->getSubheading($context),
        'headerActions' => $resourceObject->resolveHeaderActions('view', $context),
        'schema' => $resourceObject->resolveViewSchema(),
        'record' => $record,
        'entityId' => $id,
    ]);
}
```

`PageContext::viewUrl(string $id): string` returns the bare
`{prefix}/{slug}/{id}` (no action segment), the counterpart of `editUrl()`.

### 5.5 Row → view + `ViewAction`

- **`ViewAction`** (`Atrium\Table\Action\ViewAction`, peer to `EditAction`):
  label "View", icon `eye`, ability `view`, links to the bare record URL. Because
  `ActionContext::recordUrl()` appends an action segment, `ViewAction` uses a new
  bare-record helper (`ActionContext::recordRootUrl()`) rather than
  `recordUrl('view')`.
- **Clickable row:** the `DataTable` learns an optional per-row link. When the
  resource has a `'view'` page, the list makes each row link to its view URL
  (the least-destructive default). The record-actions cell stops click propagation so the
  per-row buttons (Edit/Delete) still work. The row link is suppressed when the
  user lacks `canView` for that record.
- Defaults: a resource with a view page gets `ViewAction` added ahead of `Edit` in
  the default row actions, and clickable rows; a resource **without** one is
  unchanged (no view link, rows not clickable — today's behaviour).
- **Configurable row target.** The list page controls what a row click does via
  `ListPage::recordUrl()` (or the table config): default **view** when a view page
  exists, but an integrator can point it at **edit**, a **custom URL closure**
  `fn(object $record): ?string`, or **disable** row navigation entirely (`null`) and
  rely on the explicit row actions. So the view-by-default is opinionated but
  fully overridable.

### 5.6 Configuration surface (summary)

Everything an integrator can tune, by layer — the design goal is that *every*
visible aspect is configurable, with sensible zero-config defaults:

| Layer | Knobs |
| --- | --- |
| **Existence** | Opt in per resource by registering a `'view'` page; opt a single record out via `canView()`. |
| **Page chrome** | `getHeading()` / `getSubheading()` / `getTitle()` (record-aware); `getHeaderActions()` (add/remove/reorder any `Action`/`ActionGroup`); `headerWidgets()` / `footerWidgets()` bands; `getRedirectUrl()`. |
| **Content** | `view(Schema)` of entries + layout + `Content`; or no `view()` → form rendered read-only. Fully custom Twig via a custom `ViewPage`. |
| **Layout** | `Section` (description, icon, collapsible, `aside`, compact, `columns`), `Grid`, `Fieldset`, `Tabs`, `Flex`, per-node `columnSpan`/`columnStart` — the existing layout components, reused. |
| **Per entry** | label / hidden / inline label; `columnSpan`/`align`; state via `state`/`getStateUsing`/`formatStateUsing`/`default`/`placeholder`; record-aware `visible`/`hidden`; `tooltip`/`helperText`/`hint`(+icon/color); `icon`(+position); `url`(+new tab); prefix/suffix/hint **actions**; plus each entry type's own formatting (badge, money, dateTime, image sizing, …). |
| **Navigation** | row-click target (view / edit / custom URL / off); `ViewAction` presence; view ↔ edit links. |
| **Authorization** | `canView($record)`; per-action `authorize()`; query `scopeQuery()` (out-of-scope id ⇒ 404, same as the list). |

## 6. Rendering

- New `templates/admin/view_page.html.twig` (peer to `form_page.html.twig`): the
  shell heading + header-actions slot, then the resolved schema rendered through
  the **existing** recursive layout renderer
  (`@Atrium/components/layout/components.html.twig`).
- New `templates/components/view/*.html.twig` — one small template per entry
  (`text`, `icon`, `image`, `color`, `key_value`, `repeatable`, `code`), each
  given the entry descriptor and the resolved state. `RepeatableEntry` recurses
  into the shared renderer for its nested schema.
- The fallback (no `view()`): `Field`s render via a read-only variant of the field
  wrapper — value as static text where natural, else a disabled control — reusing
  the field's existing label/layout chrome. No `data-model`, no Live binding.
- Entries inherit `columnSpan`/visibility from the shared layout machinery, so they
  sit in `Section`/`Grid` exactly like fields.

## 7. Authorization

No new concept. The controller gates `canAccess() && canView($record)`; the
existing `can('view', $record)` dispatch already maps to `canView()`. Header
actions gate on their own abilities (`edit`/`delete`); `ViewAction` and the
clickable row gate on `view`. A user with view-but-not-edit permission sees the
View screen, the Edit header button is hidden, and rows link to view.

## 8. Milestones

### VIEW-M1 — Core view screen + fallback (`VIEW-01..04`, `VIEW-11`, `VIEW-13`, `VIEW-15`, `VIEW-18`)
- `Atrium\View\Entry` base with the **full shared configuration surface**
  (`VIEW-15`: labelling/layout/align, record-aware `visible`/`hidden`, annotation,
  `icon`, `url`, prefix/suffix/hint actions — record-injected closures) + **`TextEntry`**
  (the workhorse, full formatting vocab).
- `AdminResource::view(Schema)` default + `@internal resolveViewSchema()` with the
  **form read-only fallback**; read-only field render variant.
- `ViewPage` (heading + Edit/Delete header actions); `'view'` opt-in via `pages()`;
  the custom-page escape hatch (`VIEW-18`) works by construction (render contract
  documented).
- Route `atrium_resource_view` + `AdminController::view()` + `view_page.html.twig`;
  `PageContext::viewUrl()`.
- Unit: entry state + config resolution (dotted path, `getStateUsing`,
  `formatStateUsing`, `placeholder`, record-aware `visible`/`url`),
  `resolveViewSchema` fallback. Functional:
  a resource with a `view()` of entries renders the View screen; a resource with a
  `'view'` page but no `view()` renders the form read-only; a resource without a
  `'view'` page 404s on `/{resource}/{id}`; `canView=false` ⇒ 403.

### VIEW-M2 — The rest of the entry family (`VIEW-05..08`, `VIEW-10`)
- `IconEntry`, `ImageEntry`, `ColorEntry`, `KeyValueEntry`, `CodeEntry` + templates.
- Unit tests per entry (formatting, boolean ticks, empty/placeholder, array
  key-value). Shared colour/badge vocabulary asserted consistent with `Column`.

### VIEW-M3 — Repeatable entries (`VIEW-09`)
- `RepeatableEntry` with a nested schema rendered through the shared renderer
  (`columns`/`grid`/`contained`). The one entry that recurses — isolated here.
- Unit + functional: a relation/array renders one block per item.

### VIEW-M4 — Navigation, widgets, playground, docs, verify (`VIEW-12`, `VIEW-14`, `VIEW-16`, `VIEW-17`)
- `ViewAction` + clickable-row → view (gated by `canView`); `ActionContext`
  bare-record helper; propagation-safe action cell. **Configurable row target**
  (`VIEW-17`: view/edit/custom/off) on the list page.
- **View-screen widget bands** (`VIEW-16`): `ViewPage::headerWidgets()`/
  `footerWidgets()` reusing `WidgetLayoutConfiguration`/`WidgetSlot`, with the
  record id in widget context.
- Playground: give `ProductResource` (and one relation-heavy resource) a `ViewPage`
  + a rich `view()` (badges, money, dateTime, image, a relation list, a repeatable),
  a header stat widget, and a custom row-target example; browser-verify with real
  Doctrine data, dark mode, no console errors.
- Docs: new `docs/integration-guide/pages/view.md` (or `resources/`) covering
  `view()`, the entry family + full config surface, the fallback, widget bands,
  routing/row-click and the escape hatch; cross-link from pages/resources/actions;
  CHANGELOG `VIEW-01..18`.

## 9. Testing

- **Fallback:** with no `view()`, the screen renders the `form()` fields read-only
  (no active inputs, no `data-model`); with `view()`, entries render and the form
  is not used.
- **Opt-in & routing:** `/{resource}/{id}` 404s when the resource has no `'view'`
  page; `/{resource}/new` still routes to create (order); `/{resource}/{id}/edit`
  still edits.
- **Authorization:** `canView=false` ⇒ 403; out-of-scope id ⇒ 404; Edit header
  button hidden when `canEdit=false`; row link suppressed without `canView`.
- **Entries:** each entry's state resolution + formatting, dotted-path relations,
  `placeholder` on null, `RepeatableEntry` over a relation.
- **Static render:** the view screen issues no Live Component endpoints (it is not
  reactive); header actions (Delete) still run via the action plumbing.
- Gates: `composer test && composer phpstan && composer cs` green.

## 10. Resolved decisions

1. **Entry family, unified tree, `view()` + fallback** — entries are `Atrium\View`
   leaves in the existing `Schema`, and `view()` falls back to a read-only
   `form()`.
2. **Full parity in v1** — Text/Icon/Image/Color/KeyValue/Repeatable/Code entries,
   staged across M1–M3 (Repeatable isolated last as the only recursive one).
3. **Bare URL = view; row click → view** — `/{resource}/{id}` read-only,
   `/{resource}/{id}/edit` mutable; clickable rows + `ViewAction` when a view page
   exists; everything gated by `canView`. Opt-in: a resource gets a view screen
   only by registering a `'view'` page.
4. **Reuse `Atrium\Form\Schema`** as the container now; note a possible future
   neutral-namespace extraction (non-blocking, BC-sensitive).
5. **Maximally configurable, zero-config default** (§5.6) — every visible aspect is
   tunable (page chrome, header/footer widgets, the full per-entry surface, layout,
   row-click target, authorization), yet a bare `'view' => ViewPage::class` with no
   `view()` yields a working read-only screen. Closure options are record-aware, and
   a custom `Page` is always available as the escape hatch.
