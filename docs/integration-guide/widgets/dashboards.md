# Dashboards

> Routable panel pages that arrange [widgets](overview.md) with the layout
> components. Register several; one is the panel root.

## When to use

Use a `Dashboard` to give a set of widgets a home: a finance dashboard, an ops
overview, a per-team landing page. Each dashboard is reachable at its own URL and
listed in the sidebar. Like a [page](../pages/overview.md), a dashboard is a
descriptor class, not a Live Component — the widgets it lays out are the reactive
parts.

## Example

A dashboard's `dashboard()` method configures a `DashboardConfiguration`, mirroring
how a resource's `table()`/`form()` work. The simple path is `->widgets([...])`:

```php
namespace App\Dashboard;

use App\Widget\RevenueStat;
use App\Widget\SalesChart;
use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardConfiguration;

final class FinanceDashboard extends Dashboard
{
    public function getTitle(): string
    {
        return 'Finance';
    }

    public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
    {
        return $dashboard->widgets([RevenueStat::class, SalesChart::class]);
    }

    public function getNavigationIcon(): ?string
    {
        return 'banknotes';
    }

    public function getNavigationSort(): ?int
    {
        return 10;
    }

    public function canAccess(): bool
    {
        return true; // gate via your authorization checker / voter
    }
}
```

## Arranging widgets with layout components

For anything beyond a flat stack, use `->schema([...])` and the same layout
components as a [form](../forms/layout.md) — `Grid`, `Section`, `Fieldset`,
`Flex` — with a `WidgetSlot` wrapping each widget class. There is **no top-level
`columns()`**: multiple columns come from `Grid::make(N)`, exactly as in a form.

```php
use Atrium\Dashboard\WidgetSlot;
use Atrium\Layout\Grid;
use Atrium\Layout\Section;

public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
{
    return $dashboard->schema([
        Section::make('Revenue')->schema([
            Grid::make(2)->schema([
                WidgetSlot::make(RevenueStat::class),
                WidgetSlot::make(SalesChart::class)->columnSpan(1), // half of the 2 columns
            ]),
        ]),
        WidgetSlot::make(LatestSignups::class), // full-width below the section
    ]);
}
```

A `WidgetSlot` spans **exactly like a field**: one column by default (full-width
in a stack, one cell in a `Grid`), `->columnSpan(n)` for n columns, `->columnSpanFull()`
for the whole row. Each slot renders its widget as an independent Live Component,
so refresh, polling and `canView()` keep working inside the static layout.

**Supported containers:** `Grid`, `Section`, `Fieldset`, `Flex`. `Tabs` and
`Wizard` are form-only (they are interactive Live Components bound to a form) — do
not use them in a dashboard. Container `visible()`/`hidden()` closures are
**form-only too**: they receive form state, which a dashboard has none of, so they
are not evaluated here — use [`Widget::canView()`](overview.md#shared-widget-api)
to show or hide a widget conditionally.

`WidgetSlot::make()` throws if given a class that is not a widget, so a typo fails
fast rather than rendering an empty cell.

Atrium discovers the dashboard automatically (no tags, no YAML). It is reachable at
`/admin/finance`, listed in the sidebar's **dashboards group** (above the
resources, which sit below a divider), and 403s for users `canAccess()` denies.
When an app defines no dashboard, the built-in default is shown in the group so
there is always a home entry.

## Routing & the panel root

Dashboards and resources share the `/admin/{slug}` namespace, resolved in that
order (a dashboard wins). Slugs must therefore be **unique across dashboards and
resources** — a collision fails fast with a clear error.

`/admin` (the panel root) renders the dashboard registered at the **root slug**
(`'dashboard'`), or the built-in welcome screen when none claims it. To replace
the welcome, register a dashboard whose `getSlug()` returns `'dashboard'`:

```php
final class HomeDashboard extends Dashboard
{
    public function getSlug(): string
    {
        return \Atrium\Dashboard\DefaultDashboard::ROOT_SLUG; // 'dashboard'
    }
}
```

## `Dashboard` API

| Method | Description |
| --- | --- |
| `dashboard(DashboardConfiguration $d): DashboardConfiguration` | Configure the layout — the widgets and their arrangement. Returns an empty dashboard by default. |
| `getTitle(): string` | Page heading and default nav label. Defaults from the slug. |
| `getSlug(): string` | URL slug. Defaults to the kebab-cased class name without a trailing `Dashboard`. |
| `canAccess(): bool` | Authorization gate — 403s the page and hides the nav entry. Default `true`. |
| `getNavigationLabel(): string` | Sidebar label. Defaults to the title. |
| `getNavigationIcon(): ?string` | Sidebar icon. Default `dashboard`. |
| `getNavigationSort(): ?int` | Menu order (lower first), merged with resources. Default `null` (after weighted entries). |
| `getNavigationGroup(): ?string` | Optional navigation group. |
| `getNavigationBadge(): ?string` / `getNavigationBadgeColor(): string` | Optional badge next to the entry. |
| `shouldRegisterNavigation(): bool` | Return `false` to keep the page reachable but hidden from the menu. Default `true`. |

## `DashboardConfiguration` API

| Method | Description |
| --- | --- |
| `widgets(array $classes): self` | Flat convenience — render these widget classes as a stack (wraps each in a `WidgetSlot`). |
| `schema(array $components): self` | A tree of `WidgetSlot`s and layout containers (`Grid`, `Section`, …). |
| `getComponents(): array` | The top-level nodes, for rendering. |

`widgets()` and `schema()` each set the layout — use one.

## `WidgetSlot` API

| Method | Description |
| --- | --- |
| `make(string $widgetClass): self` | Place a widget in the layout; throws if the class is not a widget. |
| `columnSpan(int\|string): self` / `columnSpanFull(): self` | Width within the parent grid (spans like a field). |

## See also

- [Widgets overview](overview.md) — the widgets a dashboard composes
- [Stats widget](stats-widget.md) · [Chart widget](chart-widget.md)
- [Embedding widgets](embedding.md) — use a widget outside a dashboard
- [Navigation](../resources/navigation.md) — the shared nav vocabulary
