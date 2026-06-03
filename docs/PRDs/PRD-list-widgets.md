# PRD — List-screen widgets (header & footer)

> Render widgets above and below a resource's list table, declared on the list
> page (or inline on the resource), composed with the same layout primitives as a
> dashboard.

Status: **proposed** · Feature IDs: `LW-01`..`LW-08`

## 1. Motivation

A list screen often wants a band of summary widgets — "Total products", "Revenue
this month", a small chart — above the table, and sometimes a note or secondary
chart below it. Today an integrator can only achieve this by abandoning the
default `/admin/{resource}` route and hand-writing a controller + template that
embeds `<twig:Atrium:Widget>`. That is a steep cliff for a common need.

The widget rendering machinery already exists (the `Atrium:Widget` host, the
`WidgetSlot` leaf, the recursive layout-component renderer built for dashboards).
This feature exposes **declarative header/footer widget slots on the standard
list screen**, so the common case needs no custom controller.

### Why first-class, given widgets are already embeddable

The entire value is "declarative, on the default list route, with no custom
controller." We explicitly accept that an integrator who wants something the
slots don't support (e.g. live filter reactivity — see §6) still drops to a
custom page. The slots cover the 90% case; they do not try to be a layout engine.

## 2. Goals

- `LW-01` — A resource's list screen can declare **header widgets** (above the
  table) and **footer widgets** (below the table).
- `LW-02` — Slots are composed with the existing layout primitives (`Grid`,
  `Section`, `Fieldset`, `Flex`) holding `WidgetSlot`s — full layout power, zero
  new rendering code.
- `LW-03` — Declared **on the list page** (`ListPage::headerWidgets()` /
  `footerWidgets()`), next to the list's heading and header actions — per-screen
  presentation lives on the page, not the resource (see §4).
- `LW-04` — Each slot widget automatically receives the **resource-identity
  context** (slug, path prefix, labels) as params, so a widget can scope its own
  query and build URLs without the integrator wiring anything.
- `LW-05` — Each widget keeps its independent lifecycle (per-widget
  `canView`, polling, refresh) because it renders through the existing
  `Atrium:Widget` host.

## 3. Non-goals

- **Filter reactivity.** Header/footer widgets do **not** recompute when the user
  searches/filters/sorts the table. They reflect the unfiltered resource. This is
  a deliberate boundary (§6), documented so no one expects reactive
  header widgets.
- **A new layout engine.** We reuse `WidgetSlot` + layout containers verbatim. No
  list-specific layout components.
- **Widgets on create/edit screens.** This feature is the *list* screen only.
  (The form screens already have header actions; widgets there are out of scope.)
- **Per-widget data hooks on the page.** Pages are descriptor classes, not
  services; widgets compute their own data via DI, exactly as today.

## 4. Decisions (from brainstorm)

| Decision | Choice |
| --- | --- |
| Reactivity | **Independent**, but widgets receive a static resource context. Live filter reactivity deferred. |
| Declaration API | A dedicated **`ListWidgetsConfiguration`** (not `DashboardConfiguration` — that would read as "this list is a dashboard"). |
| Layout power | Reuse `WidgetSlot` + layout containers via the shared renderer. |
| Where declared | **On the list page** (`ListPage::headerWidgets()`/`footerWidgets()`), with all per-screen presentation. The resource owns the entity + `table()`/`form()`. See below. |

### Where this lives — and how it got here

This design moved twice. Draft 1 mirrored header actions: a `ListPage` override
**and** an inline resource method, with null-sentinel precedence. That was
rejected as complexity for no gain. Draft 2 made it **resource-only**
(`AdminResource::headerWidgets()`). That was rejected too — it mixed per-screen
*presentation* into the resource, when headings/subheadings already live on the
page classes. The final model: **page classes own all per-screen presentation**
(heading, subheading, header actions, header/footer widgets, redirect); the
**resource owns the entity and the data-shaped `table()`/`form()`** (these are
shared/data config). To add a widget
band you write a `ListPage` subclass and point `pages()['index']` at it — the same
mechanism as a custom heading. The cost is a page subclass for the common case;
the benefit is one obvious home per concern.

This realignment also moved **header actions** onto the page model (the resource's
inline `getHeaderActions()` shortcut was removed; `ListPage::getHeaderActions()`
now owns the default "New" button), so the two are consistent.

### Naming

`ListWidgetsConfiguration` is a near-twin of `DashboardConfiguration`
(`widgets()` / `schema()` / `getComponents()`). Rather than duplicate that body,
extract it into a shared **abstract base class** —
`Atrium\Widget\WidgetLayoutConfiguration` — that both classes extend. Two
distinct, well-named `final` public types; one implementation; and a real shared
supertype the renderer/controller can type-hint where it does not care which.

The base's fluent methods return **`static`** (not `self`), so a subclass keeps
its own concrete type through a chain (`$config->schema([...])` on a
`ListWidgetsConfiguration` returns a `ListWidgetsConfiguration`). It lives in
`Atrium\Widget` — the package both `Atrium\Dashboard` and `Atrium\Page` sit above
— so the reuse is a downward dependency, never sideways.

## 5. API design

### 5.1 `ListWidgetsConfiguration`

```php
namespace Atrium\Widget;

/**
 * A layout of widgets — {@see WidgetSlot}s composed with layout containers
 * (Grid, Section, Fieldset, Flex). The shared base of the dashboard and
 * list-screen widget configurations.
 */
abstract class WidgetLayoutConfiguration
{
    /** @var list<Component> */
    private array $components = [];

    /** @param list<class-string<Widget>> $widgetClasses */
    public function widgets(array $widgetClasses): static { /* wrap each in WidgetSlot */ return $this; }

    /** @param list<Component> $components */
    public function schema(array $components): static { /* set tree */ return $this; }

    /** @return list<Component> */
    public function getComponents(): array { return $this->components; }
}
```

```php
namespace Atrium\Page;

use Atrium\Widget\WidgetLayoutConfiguration;

/**
 * The header/footer widget layout of a list screen. A distinct type from the
 * dashboard's so the intent reads correctly on a list.
 */
final class ListWidgetsConfiguration extends WidgetLayoutConfiguration
{
}
```

`DashboardConfiguration` becomes
`final class DashboardConfiguration extends WidgetLayoutConfiguration` and loses
its now-inherited body — its dashboard-specific docblock and any
dashboard-only helpers stay. The base lives in `Atrium\Widget`; the two concrete
configs live with their consumers (`Atrium\Dashboard`, `Atrium\Page`).

### 5.2 On the list page

Declared on a `ListPage` subclass, next to the list's heading and header actions.
Empty by default on the base `ListPage`; override to add a band, then point the
resource's `pages()['index']` at the subclass:

```php
namespace App\Admin\Pages;

use Atrium\Layout\Grid;
use Atrium\Page\ListPage;
use Atrium\Page\ListWidgetsConfiguration;
use Atrium\Widget\WidgetSlot;

final class ProductList extends ListPage
{
    public function headerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config->schema([
            Grid::make(3)->schema([
                WidgetSlot::make(ProductCountWidget::class),
                WidgetSlot::make(RevenueWidget::class),
                WidgetSlot::make(LowStockWidget::class),
            ]),
        ]);
    }
    // footerWidgets() likewise; omit for none.
}
```

```php
// ProductResource
public static function pages(): array
{
    return ['index' => ProductList::class, /* create/edit … */];
}
```

### 5.3 No precedence

There is one home: the `ListPage`. The base returns an empty config, a subclass
fills it. No inline resource method, no null-sentinel, no `instanceof`-driven
fallback — the resolver simply asks the index page.

### 5.4 Resolution (internal)

A `final @internal resolveHeaderWidgets()/resolveFooterWidgets()` on
`AdminResource` (the `pages()` owner) asks the index page and bakes the
resource-identity context onto every slot:

```php
final public function resolveHeaderWidgets(PageContext $context): ListWidgetsConfiguration
{
    $page = $this->resolvePage('index');
    $config = $page instanceof ListPage ? $page->headerWidgets(new ListWidgetsConfiguration()) : new ListWidgetsConfiguration();

    return $config->applyContext($this->widgetContext($context));
}
```

### 5.5 Context injection

When the slot tree is rendered, the framework injects resource-identity params
into **each** `WidgetSlot`'s widget params: `resource` (slug), `pathPrefix`, and
the singular/plural labels — the same facts `PageContext` carries. A widget reads
them via its existing param accessor to scope a query or build a URL. No new
public API on `Widget`; this rides the existing `params` channel.

Concretely the `WidgetSlot` template already renders
`<twig:Atrium:Widget :widget="..." :params="..." />`; the list-screen render
passes a base params map down, merged under any per-widget params.

## 6. The reactivity boundary (the one trade-off)

**Why widgets can't reflect the table's live filters:** the table's interactive
state — `filterValues`, `sortField`, `sortDirection`, `page`, `perPage` — lives
as LiveProps **inside the `Atrium:DataTable` component**. Only `search` is
`url: true`. When the controller renders the list page (and the widget slots),
the DataTable has not mounted: filters are empty, sort/page at defaults. Each
widget is a **sibling** Live Component that mounts independently and never sees
the table's state.

Bridging that — DataTable emits its resolved criteria, widgets listen and
recompute — is a genuine cross-component contract (a `DataQuery` would have to
cross the component boundary, and every slot widget would need a criteria-aware
data path). That is a separate, larger feature.

**Forward compatibility:** the static context (§5.5) is delivered through the
widget `params` channel. A future reactive version delivers *live* criteria
through the **same** channel, so a widget written today against `params` would
not change — it would simply start receiving filter state. We are not painting
ourselves into a corner.

**Documented expectation:** a header widget showing "12 products" does not tick to
"3" when the user filters the table to three rows. It reflects the resource, not
the current view.

## 7. Rendering

- `templates/admin/resource.html.twig` (the list screen) gains two slots: one
  before `<twig:Atrium:DataTable>`, one after.
- Each slot renders its `ListWidgetsConfiguration` component tree through the
  existing `@Atrium/components/layout/components.html.twig` with a `grid-cols-1`
  base (identical to how the dashboard renders its tree).
- A slot with an empty config renders nothing (no empty band, no wrapper).

## 8. Milestones

### LW-M1 — Core
- `WidgetLayoutConfiguration` abstract base extracted from
  `DashboardConfiguration` (refactor: `DashboardConfiguration` now `extends` it,
  fluent methods return `static`; behaviour unchanged, existing dashboard tests
  stay green).
- `ListWidgetsConfiguration extends WidgetLayoutConfiguration`.
- `ListPage::headerWidgets()/footerWidgets()` defaults (empty) +
  `AdminResource`'s `final @internal resolveHeaderWidgets()/resolveFooterWidgets()`
  that ask the index page and bake the resource-identity context onto every slot.
- Header-actions realignment: `Page::getHeaderActions()` → non-nullable `array`;
  `ListPage::getHeaderActions()` owns the default "New" button; the resource's
  inline `getHeaderActions()` shortcut is removed (its `resolveHeaderActions()`
  now delegates only to the page).
- Controller passes both resolved configs to the list template; template renders
  the two slots with context-param injection.
- Unit tests: configuration shape, context-param injection (incl. nested slots),
  page-owned header-action defaults. Functional: a resource whose list page
  declares header+footer widgets renders both bands around the table (header
  above, footer below); a plain resource renders neither.

### LW-M2 — Playground + docs + verify
- Playground: `ProductResource` gets a header band (a stat + a chart) and a small
  footer note widget; browser-verify around the real table with Doctrine, no
  console errors.
- Docs: new `docs/integration-guide/tables/list-widgets.md` (or a section on the
  pages overview — decide in M2); cross-link from tables + pages; CHANGELOG
  `LW-01..08`. Explicitly document the §6 non-reactivity boundary.

## 9. Testing

- **Base-class refactor is behaviour-preserving:** the existing dashboard suite
  must stay green after `DashboardConfiguration` extends
  `WidgetLayoutConfiguration`.
- **Precedence:** a `ListPage` subclass overriding `headerWidgets()` wins; with no
  page, the resource's inline method is used; with neither, no band renders.
- **Context injection:** a stub widget asserts it received `resource`/`pathPrefix`
  params from the slot render.
- **Rendering:** functional test boots the kernel, renders the list screen for a
  fixture resource with header+footer widgets, asserts both bands present and in
  the right order relative to the table; a resource with no widgets renders
  neither band.
- Gates: `composer test && composer phpstan && composer cs` green.

## 10. Resolved decisions

1. **Page-owned, no precedence** (§4, §5.2–5.4). Declared on a `ListPage`
   subclass, with all per-screen presentation; the resource owns the entity +
   `table()`/`form()`. (Iterated: dual+precedence → resource-only → page-owned.)
   Header actions were realigned the same way in this pass.
2. **Footer — shipped in v1.** Both header and footer slots; the marginal cost is
   one method + one template slot.
3. **Docs home — dedicated `docs/integration-guide/tables/list-widgets.md`**,
   cross-linked from `pages/overview.md` and `tables/table-configuration.md`.
