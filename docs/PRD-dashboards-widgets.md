# Atrium — Dashboards & Widgets (PRD addendum)

> **Status:** Proposed · **Type:** Addendum to [`docs/PRD.md`](./PRD.md)
> **Extends:** §8.7 Widgets (`WGT`), §8.8 Panel shell (`PNL`), §9 DX contract,
> Phase 5
> **Supersedes nothing** — all existing routing, navigation and resource
> behaviour is preserved; the current `/admin` welcome screen becomes a
> replaceable default dashboard.

This document scopes Atrium's **dashboard and widget layer**, taking the proven
ideas from Filament's
[Widgets](https://filamentphp.com/docs/5.x/widgets/overview) and
[multiple dashboards](https://filamentphp.com/docs/5.x/widgets/overview#creating-multiple-dashboards)
docs and adapting them to Atrium's architecture (server-driven Live Components,
no client framework, no Doctrine in core, the inline path stays valid, downward
dependencies only).

It realizes the **widget half of PRD Phase 5**. It extends the existing **`WGT`**
namespace from `WGT-03`, introduces a new **`DSH`** (dashboards) namespace, and
extends **`PNL`** (panel shell) for the routing/navigation generalization. IDs are
referenceable from commits/PRs exactly like the main PRD.

---

## 1. Motivation

Today the panel has exactly one dashboard: a hardcoded `/admin` route whose
controller renders a static welcome card plus a grid of links to registered
resources. There is **no widget concept** and **no way to route or navigate to a
non-resource screen**. An integrator who wants "show me revenue and a sales chart
on the landing page", or "give the finance team their own dashboard", has nowhere
to put it.

Filament's model is two coupled abstractions:

- A **Widget** is a self-contained, independently-refreshing UI unit — a stat
  card, a chart, a small table.
- A **Dashboard** is a routable page that lays widgets out in a responsive grid;
  an app can have **several**, each with its own slug, navigation entry and
  authorization.

Atrium already has every primitive needed to build this in its own idiom — a
descriptor/Live-Component split (as in `DataTable`/`Form`), a tag-based registry
(`ResourceRegistry`), parametric routing, and the resource navigation vocabulary.
This addendum assembles them into widgets and dashboards **without** importing a
client framework or leaking data-store types into core.

## 2. Goals

- A `abstract class Widget` and a small concrete set — **`StatsWidget`** (stat
  cards) and **`ChartWidget`** (Symfony UX Chart.js) — configured entirely in PHP.
- Widgets are **independently reactive** (`WGT-02`): each can refresh on demand or
  on a poll interval without reloading the page or its neighbours.
- Widgets are **embeddable anywhere** — inside a dashboard grid, or in any Twig
  template via a single component tag — with optional scalar context.
- A `abstract class Dashboard` that composes widgets into a responsive grid, is
  **routable**, appears in **navigation**, and is **authorizable**; an app may
  register **multiple** dashboards.
- Generalize panel routing/navigation so dashboards and resources **coexist**
  under the panel, with the existing `/admin` screen preserved as a default
  dashboard.
- Keep core **storage-agnostic**: widgets compute their own data from
  integrator-injected services; the framework adds **no** aggregation API.
- Keep every capability **additive**: an app that registers no dashboards and no
  widgets behaves exactly as today.

## 3. Non-goals (v1 of this addendum)

- **Table widgets** (embedding a `DataTable` as a widget). Deferred; the tables
  subsystem reuse and its edge cases warrant their own slice.
- **Dashboard filter forms** (Filament's `HasFiltersForm` / `InteractsWithPageFilters`).
  Deferred; ad-hoc scalar `params` on an embedded widget cover the simple case.
- **A first-class "resource header widgets" API** (`getHeaderWidgets()`). The
  embed-anywhere Twig tag already lets an integrator place a widget above a list
  by hand; a dedicated API can follow.
- **A core aggregation / query-builder API.** Widgets receive already-computed
  values from their own injected services (§6). Adding `sum()/avg()/groupBy()` to
  `DataProviderInterface` would widen a stable contract and risk an over-fit DSL.
  *Noted as a possible future, backend-specific helper.*
- **Client-side (Alpine/JS) widget logic.** Refresh and polling are
  **server-evaluated** Live Component round-trips, consistent with PRD §5.2 and
  CLAUDE.md #1.
- **Stat sparklines, widget lazy/deferred loading, per-widget result caching.**
  Deferred; polling covers the refresh need for v1.

## 4. Architecture

### 4.1 The descriptor + generic-host pattern (chosen)

Widgets follow the **same split Atrium already uses for `DataTable` and `Form`**:
the integrator writes a *plain descriptor service*; a single *generic Live
Component* renders it reactively.

- **Author-facing class is a descriptor service**, not a Live Component. It is a
  normal service (DI-injectable, unit-testable in isolation) tagged
  `atrium.widget`. This upholds CLAUDE.md #5 (author classes are descriptors;
  reactive transport is a separate generic component).
- **One generic host Live Component** — `atrium:widget` — takes the descriptor's
  **class name** as a non-writable (HMAC-checksummed, unforgeable) LiveProp,
  resolves it from a `WidgetRegistry`, enforces authorization, calls its data
  methods, and renders the descriptor's view. There is exactly **one** host class
  regardless of how many widget types exist.

Two alternatives were rejected:

- **Each widget IS a Live Component** (Filament's literal shape). Closest to
  Filament, but every widget would drag HMAC/hydration machinery, become harder to
  unit-test, and break Atrium's descriptor/component split.
- **Server-rendered partials with dashboard-level refresh only.** Simplest, but
  violates `WGT-02` (independent refresh) and the embed-anywhere goal.

### 4.2 Components

| Component | Kind | Responsibility |
| --- | --- | --- |
| `Atrium\Widget\Widget` | abstract descriptor | Base for all widgets: column span, `canView()`, polling, view selection. |
| `Atrium\Widget\StatsWidget` | abstract descriptor | Produces a list of `Stat` value objects. |
| `Atrium\Widget\ChartWidget` | abstract descriptor | Produces a UX Chart.js chart (type/data/options). |
| `Atrium\Widget\Stat` | `final readonly` value object | One stat card: label, value, description, icon, color, url. |
| `Atrium\Widget\WidgetRegistry` | service | Resolves tagged widget descriptors by class name. |
| `Atrium\Twig\Components\Widget` | Live Component (`atrium:widget`) | The generic reactive host: mount, authorize, refresh, poll, render. |
| `Atrium\Dashboard\Dashboard` | abstract descriptor | A routable page composing widgets in a grid; nav + auth. |
| `Atrium\Dashboard\DashboardRegistry` | service | Resolves tagged dashboards by slug; collision detection vs resources. |
| `Atrium\Dashboard\DefaultDashboard` | descriptor | Ships at `/admin`; renders the current welcome + resource cards. |

Dependencies stay **downward**: `Dashboard` → `Widget` descriptors →
contracts/`DataProviderInterface`. No widget package depends sideways on tables or
forms. `ChartWidget` depends on `symfony/ux-chartjs` (a UI dependency that ships
its Stimulus controller via AssetMapper — **no JS build step** for consumers).

### 4.3 Data flow

1. Request `/admin/finance` → `AdminController` resolves `FinanceDashboard` from
   `DashboardRegistry`, calls `denyUnless($dashboard->canAccess())`, renders the
   dashboard host template.
2. The template lays out a CSS grid sized by `getColumns()` and renders one
   `<twig:atrium:widget class="…">` per `getWidgets()` entry, each spanning
   `getColumnSpan()`.
3. Each `atrium:widget` mounts → resolves the descriptor from `WidgetRegistry` →
   enforces `canView()` → calls `getStats()` / `getData()` → renders the
   descriptor's stats/chart sub-template. The descriptor's **constructor-injected
   services** (the integrator's repository, `DataProviderInterface`, a DBAL
   connection, …) perform the actual data access.
4. **Independent refresh** (`WGT-02`): a `refresh` LiveAction re-runs the
   descriptor's data methods and morphs only that widget's DOM. An opt-in
   `getPollingInterval()` drives the same refresh on a timer.

## 5. Requirements

### 5.1 Widgets — `WGT` (extends WGT-01/02)

- **WGT-03** `abstract class Widget` descriptor base, autoconfigured via the
  `atrium.widget` tag, resolvable from `WidgetRegistry` by class name.
- **WGT-04** Generic host Live Component `atrium:widget` renders any registered
  widget; the widget class is a non-writable, checksummed LiveProp (unforgeable).
- **WGT-05** Widgets are embeddable in **any Twig template** via
  `<twig:Atrium:Widget widget="…" :params="{…}">`; `params` carries optional
  **scalar** context (ids, ranges, filter values — never hydrated entities). The
  class is passed as `widget` (not `class`, which Twig reserves for CSS classes).
- **WGT-06** Each widget refreshes **independently** via a `refresh` action and an
  optional `getPollingInterval(): ?string` (e.g. `'10s'`; default `null`).
- **WGT-07** Per-widget authorization: `canView(): bool` (an instance method, so
  it can use the widget's injected services), enforced server-side on **mount and
  refresh**; a hidden widget renders nothing and is skipped in a dashboard grid.
- **WGT-08** Grid sizing: `getColumnSpan(): int|array` (responsive, e.g.
  `['md' => 2, 'xl' => 3]`).
- **WGT-09** `StatsWidget`: `getStats(): array` of `Stat` value objects.
  `Stat::make($label, $value)` with fluent `->description()`,
  `->descriptionIcon()`, `->color()`, `->url()`.
- **WGT-10** `ChartWidget`: `getType()`, `getData()`, `getOptions()`,
  `getHeading()`, built into a chart via Symfony UX Chart.js; renders with no JS
  build step. Empty data renders an empty state.

### 5.2 Dashboards — `DSH`

- **DSH-01** `abstract class Dashboard` descriptor, autoconfigured via the
  `atrium.dashboard` tag, resolvable from `DashboardRegistry` by slug.
- **DSH-02** `getWidgets(): array` (list of widget class strings) +
  `getColumns(): int|array` (responsive grid) define the layout.
- **DSH-03** Routable: a dashboard is reachable at `/admin/{slug}` (the default
  dashboard at `/admin`); slug defaults from the class name, overridable.
- **DSH-04** Navigable: reuses the resource navigation vocabulary
  (`getNavigationLabel/Icon/Sort/Group/Badge/BadgeColor`,
  `shouldRegisterNavigation()`); dashboard and resource nav entries are **merged
  and sorted together**.
- **DSH-05** Authorizable: `canAccess(): bool` (an instance method, matching
  `AdminResource::canAccess()`); a forbidden dashboard 403s on direct navigation
  and is omitted from the menu — identical to resources.
- **DSH-06** Multiple dashboards are supported. `/admin` always renders the
  **root-slug** dashboard; additional dashboards live at `/admin/{slug}`.
- **DSH-07** A built-in `DefaultDashboard` occupies the root slug by default —
  preserving today's `/admin` welcome + resource-cards screen — and is
  **replaceable** by registering another dashboard at the root slug.

### 5.3 Panel shell — `PNL` (extends PNL-01/02)

- **PNL-06** Generalize the parametric route so `/admin/{slug}` resolves to a
  **dashboard or a resource** (dashboard checked first), 404 if neither.
- **PNL-07** Slugs are **unique across dashboards and resources**; a collision is
  detected **fast** (a compiler pass / registry build error), never silently.
- **PNL-08** The sidebar is generated from **both** registries, sorted by the
  shared navigation-sort vocabulary, grouped by nav group.

## 6. Developer-facing API contract (DX spec)

The framework's "configured in PHP" promise is the product. Signatures below are
the public contract; treat changes as BC-relevant.

### 6.1 A stat widget (integrator computes its data)

```php
namespace App\Widget;

use App\Repository\OrderRepository;
use Atrium\Widget\Stat;
use Atrium\Widget\StatsWidget;

final class RevenueStat extends StatsWidget
{
    // Widgets are services: inject whatever produces the numbers.
    public function __construct(private OrderRepository $orders)
    {
    }

    public function getStats(): array
    {
        return [
            Stat::make('Revenue (30d)', '$'.number_format($this->orders->revenueLast(30)))
                ->description('+12% vs prior')
                ->descriptionIcon('arrow-trending-up')
                ->color('success'),
            Stat::make('Orders', (string) $this->orders->countLast(30)),
        ];
    }

    public function getColumnSpan(): int|array
    {
        return ['md' => 1];
    }

    public function getPollingInterval(): ?string
    {
        return '30s';
    }
}
```

### 6.2 A chart widget

```php
namespace App\Widget;

use App\Repository\OrderRepository;
use Atrium\Widget\ChartWidget;

final class SalesChart extends ChartWidget
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function getHeading(): ?string
    {
        return 'Sales per month';
    }

    public function getType(): string
    {
        return 'line';
    }

    public function getData(): array
    {
        $byMonth = $this->orders->salesByMonth(); // ['Jan' => 1200, …]

        return [
            'labels' => array_keys($byMonth),
            'datasets' => [[
                'label' => 'Sales',
                'data' => array_values($byMonth),
            ]],
        ];
    }
}
```

### 6.3 A dashboard composing them

```php
namespace App\Dashboard;

use App\Widget\RevenueStat;
use App\Widget\SalesChart;
use Atrium\Dashboard\Dashboard;

final class FinanceDashboard extends Dashboard
{
    public function getTitle(): string
    {
        return 'Finance';
    }

    public function getWidgets(): array
    {
        return [RevenueStat::class, SalesChart::class];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
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
        return true; // gate via your voter/authorization checker
    }
}
```

Reached at `/admin/finance`, listed in the sidebar (sorted among resources), 403
for users `canAccess()` denies.

### 6.4 Embedding a widget anywhere

```twig
{# In any Twig template — a record page, a custom report, an email preview…
   `class` is reserved by Twig components for CSS classes, so the widget class is
   passed as the `widget` attribute. #}
<twig:Atrium:Widget widget="App\\Widget\\RevenueStat" />

{# With scalar context the descriptor can read from $params #}
<twig:Atrium:Widget widget="App\\Widget\\OrdersPerDay" :params="{ range: 90 }" />
```

### 6.5 API reference (selected)

`Atrium\Widget\Widget` (abstract)

| Method | Description |
| --- | --- |
| `getColumnSpan(): int\|array` | Grid width; int or responsive map. Default `1`. |
| `canView(): bool` | Authorization gate (instance method); default `true`. |
| `getPollingInterval(): ?string` | Auto-refresh interval (e.g. `'10s'`); default `null`. |

`Atrium\Widget\Stat` (`final readonly`)

| Method | Description |
| --- | --- |
| `make(string $label, string\|int $value): self` | Create a stat. |
| `description(string): self` / `descriptionIcon(string): self` | Sub-line + icon. |
| `color(string): self` | Semantic color (`primary`/`success`/`danger`/…). |
| `url(string): self` | Make the card a link. |

`Atrium\Dashboard\Dashboard` (abstract)

| Method | Description |
| --- | --- |
| `getSlug(): string` | URL slug; defaults from class name. |
| `getTitle(): string` | Page + default nav label. |
| `getWidgets(): array` | Widget class strings to render. |
| `getColumns(): int\|array` | Grid columns; responsive map allowed. |
| `canAccess(): bool` | Authorization gate (instance method); default `true`. |
| `getNavigation*()` / `shouldRegisterNavigation()` | Same vocabulary as resources. |

## 7. Security

- **Unforgeable widget identity.** The host's `widgetClass` LiveProp is
  **non-writable** → HMAC-checksummed by UX Live Components → a client cannot swap
  it to instantiate an arbitrary class.
- **Registry-only resolution (defense in depth).** Even an authentic class name is
  only resolved if it is a registered `atrium.widget` service; unknown classes
  fail closed (render nothing, log).
- **Authorization enforced server-side, twice.** `Widget::canView()` is checked on
  **mount and on every refresh** (a writable interaction cannot bypass it).
  `Dashboard::canAccess()` is checked in the controller (`denyUnless`) on every
  request, mirroring the resource gates.
- **Scalar-only `params`.** Embed context is scalars/arrays of scalars; no entity
  hydration across the wire — descriptors re-fetch via their injected services.

## 8. Error handling

| Condition | Behaviour |
| --- | --- |
| Forged / unknown widget class | Host renders empty + logs; doubly guarded by checksum + registry. |
| `canView()` false | Widget renders nothing; omitted from the dashboard grid. |
| `canAccess()` false on a dashboard | `403` on navigation; omitted from the menu. |
| Slug collision (dashboard vs resource) | Fail-fast at container build (compiler pass / registry build). |
| Chart with no data | Chart sub-template shows an empty state. |
| Unknown slug at `/admin/{slug}` | `404`, as today. |

## 9. Testing strategy

- **Unit:** `Stat` value object; `StatsWidget`/`ChartWidget` descriptors in
  isolation (plain services — `getStats()`/`getData()` without the component
  layer); `WidgetRegistry` and `DashboardRegistry` resolution; slug derivation;
  slug-collision detection.
- **Functional (kernel boot):** dashboard route renders; nav merges + sorts
  dashboards and resources; `canAccess()` 403; default dashboard at `/admin`;
  `atrium:widget` mount renders; `refresh` action returns morphed HTML; forged
  `widgetClass` rejected; `canView()` hides a widget.
- **Browser (playground):** a real second dashboard ("Insights") with a
  `StatsWidget` + a line `ChartWidget`, plus one ad-hoc Twig embed — assert no
  console errors, the chart renders, refresh updates a stat, and both dashboards
  appear in the sidebar.

## 10. Documentation

A new integration-guide module **`docs/integration-guide/widgets/`**:
`overview.md`, `stats-widget.md`, `chart-widget.md`, `dashboards.md`,
`embedding.md`. Each follows the canonical template (summary · when to use ·
runnable example · API reference · see-also). The widgets module joins the six
existing ones in the guide index and README. The "integrator computes the data"
recipe (with a Doctrine example) is documented prominently, since it is the
primary data path.

## 11. Dependencies

- **`symfony/ux-chartjs`** — new downward UI dependency, required by
  `ChartWidget`. Ships its Stimulus controller through AssetMapper/importmap, so
  consumers still have **no JS build step**. In the single-bundle phase it is a
  `require`; on the eventual monorepo split it belongs to `atrium/admin-widgets`.

## 12. Implementation milestones

A suggested slice order (each independently shippable, gates green per CLAUDE.md):

1. **Widget core** — `Widget`/`Stat`/`StatsWidget`, `WidgetRegistry` + tag, the
   `atrium:widget` host (mount, `canView`, `refresh`), stats template + CSS.
   Tests + a playground embed.
2. **Chart widget** — `ChartWidget` + UX Chart.js wiring, chart template, polling.
   Tests + a playground chart.
3. **Dashboards & routing** — `Dashboard`/`DashboardRegistry` + tag,
   `DefaultDashboard`, the generalized `/admin/{slug}` dispatch, slug-collision
   compiler pass, merged navigation. Tests + a second playground dashboard.
4. **Docs** — the `widgets/` guide module + README/index, `CHANGELOG`, and a note
   in `docs/PRD.md` §8.7 / Phase 5 pointing here.

## 13. Acceptance criteria

- An app can declare `RevenueStat`, `SalesChart` and `FinanceDashboard` purely in
  PHP and reach `/admin/finance` with both widgets in a responsive grid.
- Both dashboards appear in the sidebar, correctly sorted among resources; an
  unauthorized user sees neither the entry nor the page (403).
- A widget refreshes (manually and on its poll interval) without reloading the
  page or its neighbours; the chart renders with no JS build step.
- A widget embeds in an arbitrary Twig template via `<twig:atrium:widget>`.
- A forged or unregistered widget class cannot instantiate arbitrary code.
- A dashboard/resource slug collision fails the container build.
- `composer test && composer phpstan && composer cs` are green; every new public
  component is documented in `docs/integration-guide/widgets/`.
