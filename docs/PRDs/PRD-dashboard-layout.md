# Atrium — Dashboard layout (PRD addendum)

> **Status:** Proposed · **Type:** Addendum to
> [`docs/PRDs/PRD-dashboards-widgets.md`](./PRD-dashboards-widgets.md)
> **Extends:** §8.7 Widgets (`WGT`), Dashboards (`DSH`), §8.11 Layout (`LAY`),
> §9 DX contract
> **Supersedes:** the just-introduced `Dashboard::getWidgets()` /
> `Dashboard::getColumns()` flat API and `Widget::getColumnSpan()` — both
> unreleased, so this is an internal migration, not an external BC break.

This document lets an integrator **arrange dashboard widgets with Atrium's layout
components** (`Grid`, `Section`, `Fieldset`, `Flex`) instead of a flat list, by
making widgets first-class nodes in the existing layout tree. It introduces a
`DashboardConfiguration` (mirroring the form `Schema`) and a `WidgetSlot` leaf,
and replaces `Dashboard::getWidgets()` with `dashboard(DashboardConfiguration)`.

It extends **`DSH`** from `DSH-08` and **`WGT`** at `WGT-11`.

---

## 1. Motivation

`Dashboard` currently returns a flat `getWidgets(): array` laid out by
`getColumns()`. That covers "a row of widgets" but not "a *Revenue* section with a
2-up grid of charts above a full-width table." Atrium already has the machinery
for exactly this — its **layout subsystem is view-agnostic by design**. The
`Atrium\Layout\Component` contract documents it verbatim:

> *Form fields, layout containers (Grid, Section, Fieldset) and — in future —
> dashboard widgets or infolist entries all implement this contract, so the same
> grid/section machinery powers forms, dashboards and other views.*

The container templates (`section/grid/fieldset/flex.html.twig`) and the recursive
renderer (`components.html.twig`) are confirmed form-agnostic — they render purely
from each node's own accessors, with no form state. The only missing piece is a
**leaf that adapts a widget into that tree**, plus a configuration object to hold
it. This addendum supplies both, so dashboards compose with the same primitives as
forms — and sets up the eventual **infolists** feature to reuse them too.

## 2. Goals

- Let a dashboard arrange widgets with `Grid` / `Section` / `Fieldset` / `Flex`,
  reusing the existing layout components and renderer (no parallel system).
- Mirror the form `Schema` shape: a `DashboardConfiguration` with a flat
  convenience (`widgets()`) and a tree (`schema()`), no redundant top-level
  `columns()` — multiple columns come from `Grid::make(N)`, exactly as in forms.
- Keep widgets as **independent Live Component islands** inside static layout
  chrome: per-widget refresh, polling and `canView()` are untouched.
- Make width a **placement** concern owned by the layout (`WidgetSlot` column
  span, like a field), not a property of the widget descriptor.
- Fail fast on obvious mistakes (a non-widget class in a slot).

## 3. Non-goals (v1 of this addendum)

- **A top-level `columns()` on the dashboard.** Redundant with `Grid::make(N)`;
  the form `Schema` has none either. Omitted deliberately.
- **Interactive containers in dashboards — `Tabs`, `Wizard`.** They are
  Live Components bound to the Form component's state/stepper. Reusing them
  standalone is a separate effort. The supported container set is **Grid,
  Section, Fieldset, Flex**; the others are documented as form-only.
- **`Get`-based reactive visibility on dashboards.** `visible()`/`hidden()`
  closures receive form state, which a dashboard has none of, so they are **not
  evaluated** outside a form. Conditional widgets use `Widget::canView()`. (A
  container `visible(false)` on a dashboard is a no-op — see §7.)
- **Plain-bool container visibility on dashboards.** Out of scope for v1; revisit
  if asked. Today, dashboards render every component in the tree.

## 4. Design

### 4.1 `Atrium\Dashboard\DashboardConfiguration`

A fluent configuration, the dashboard analogue of `Schema` (panel layer — may
depend on Layout and on widget class strings; no sideways builder dependency):

| Method | Description |
| --- | --- |
| `widgets(array $classes): self` | Flat convenience — wrap each widget class string in a `WidgetSlot` (the simple path). |
| `schema(array $components): self` | A mixed tree of `WidgetSlot`s and layout containers (the same verb the layout containers use). |
| `getComponents(): list<Component>` | The top-level nodes, for rendering. |

`widgets()` and `schema()` each set the tree — use one. The naming mirrors the
layout containers (`Grid::make(2)->schema([...])`), so nesting reads uniformly.

No `getColumns()` / `getColumnsClass()`: the root renders as a single-column
stack (like a form), and columns come from a nested `Grid`.

### 4.2 `Atrium\Dashboard\WidgetSlot`

The leaf that places a widget in the layout tree. Implements
`Atrium\Layout\Component`; uses `HasColumnSpan` + `HasGrow`, so it spans and grows
**exactly like a field** (default: one column — full-width in a stack, one cell in
a `Grid`).

| Method | Description |
| --- | --- |
| `make(string $widgetClass): self` | Create a slot for a widget class. Guards with `is_a($class, Widget::class, true)` and throws on a non-widget class (fail fast). |
| `columnSpan(int\|string): self` / `columnSpanFull(): self` | Placement width within the parent grid (from `HasColumnSpan`). |
| `getWidgetClass(): string` | The wrapped class (for the renderer). |
| `getChildComponents(): array` | `[]` — a leaf. |
| `getTemplate(): string` | A small template rendering `<twig:Atrium:Widget :widget="…">`. |

Lives in `Atrium\Dashboard` (not `Atrium\Widget`) so the bridge to
`Atrium\Layout\Component` is a downward panel→builder dependency, never a sideways
widget↔layout one.

### 4.3 `Dashboard::dashboard()` replaces `getWidgets()`/`getColumns()`

```php
public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
{
    return $dashboard;
}
```

Mirrors `AdminResource::table(TableConfiguration $t): TableConfiguration` and
`form(Schema $s): Schema`: the framework hands the descriptor a configuration, the
descriptor populates and returns it. `getWidgets()`, `getColumns()` and
`getColumnsClass()` are removed from `Dashboard`.

### 4.4 Widget column span moves to the layout (`WGT-11`)

`Widget::getColumnSpan()` / `getColumnSpanClass()` and the `StatsWidget` /
`ChartWidget` `'full'` overrides are **removed**; the host no longer applies a
span class on its root. Width is owned by `WidgetSlot` (in a layout) or is simply
full (a standalone `<twig:Atrium:Widget>` embed sits in normal document flow; wrap
it yourself to size it). This removes two competing owners of width and the nested
double-span it would cause.

### 4.5 Rendering

The controller resolves the configuration
(`$dashboard->dashboard(new DashboardConfiguration())`) and renders its component
tree through the **existing** generic renderer
(`@Atrium/components/layout/components.html.twig`) as a single-column stack. Each
`WidgetSlot` leaf renders an independent `<twig:Atrium:Widget>` host; containers
recurse through their existing templates. The dashboard page is a plain
controller-rendered page embedding N Live Components — the layout is static chrome,
the widgets are the reactive parts.

## 5. Requirements

- **DSH-08** `Dashboard::dashboard(DashboardConfiguration): DashboardConfiguration`
  replaces `getWidgets()`/`getColumns()`; mirrors `table()`/`form()`.
- **DSH-09** `DashboardConfiguration` — `widgets()` (flat), `schema()` (tree),
  `getComponents()`; no top-level columns.
- **DSH-10** `WidgetSlot` — a `Component` leaf wrapping a widget class, rendering
  the host; spans like a field; `make()` fails fast on a non-widget class.
- **DSH-11** A dashboard renders its component tree via the shared layout renderer,
  reusing **Grid / Section / Fieldset / Flex**. Widgets stay independent Live
  Components inside the static layout.
- **WGT-11** Column span is removed from the `Widget` descriptor; placement is
  owned by `WidgetSlot` / the layout.

## 6. Developer-facing API contract (DX spec)

```php
use App\Widget\RevenueStat;
use App\Widget\SalesChart;
use App\Widget\LatestSignups;
use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardConfiguration;
use Atrium\Dashboard\WidgetSlot;
use Atrium\Layout\Grid;
use Atrium\Layout\Section;

final class OverviewDashboard extends Dashboard
{
    public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
    {
        return $dashboard->schema([
            Section::make('Revenue')->schema([
                Grid::make(2)->schema([
                    WidgetSlot::make(RevenueStat::class),
                    WidgetSlot::make(SalesChart::class),
                ]),
            ]),
            WidgetSlot::make(LatestSignups::class), // full-width below
        ]);
    }
}
```

The flat path stays trivial:

```php
public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
{
    return $dashboard->widgets([RevenueStat::class, SalesChart::class]);
}
```

## 7. Risks & mitigations

| Risk | Severity | Mitigation |
| --- | --- | --- |
| Container `visible()`/`hidden()` (form-state) is **not evaluated** on a dashboard, so `->visible(false)` is a silent no-op. | Medium | Documented as form-only; conditional widgets use `Widget::canView()`. Fail-safe (renders), never errors. |
| `Tabs`/`Wizard` are `Component`s, so an integrator *can* drop them into a dashboard, where they break confusingly. | Medium | Document the supported container set (Grid/Section/Fieldset/Flex). Not blocked at the type level (shared interface). |
| A typo'd / non-widget class in a slot fails closed (empty), no error. | Low | `WidgetSlot::make()` guards with `is_a($class, Widget::class, true)` and throws. |
| Removing `Widget::getColumnSpan()` is a surface change (host, stats/chart, docs, playground). | Low | Pre-release; updated consistently in this change. |
| Nested cards (`Section` → `Grid` → widget cards) can look heavy. | Low | Tune padding; browser-verify. |
| A rich dashboard mounts many Live Components; several polling = request fan-out. | Low | Docs note; unchanged correctness. |

## 8. Testing strategy

- **Unit:** `DashboardConfiguration` (`widgets()` wraps to slots; `components()`
  tree; `getComponents()`); `WidgetSlot` (template, span like a field, `is_a`
  guard throws on a non-widget class, leaf has no children).
- **Functional (kernel boot):** a dashboard whose `dashboard()` nests
  `Section` + `Grid` + `WidgetSlot`s renders the section heading, the grid, and
  each widget's content; the flat `widgets()` path renders a stack; each widget is
  still an independent, refreshable Live Component.
- **Browser (playground):** the Insights dashboard rebuilt with a `Section` + a
  2-up `Grid` (stat + chart) — assert no console errors, the chart paints, the
  layout nests, and a widget still refreshes independently.

## 9. Documentation

Update `docs/integration-guide/widgets/dashboards.md` (the `dashboard()` method,
`DashboardConfiguration`, `WidgetSlot`, the supported container set, the
visibility caveat) and the three widget pages that mention `getColumnSpan()`
(`overview.md`, `stats-widget.md`, `chart-widget.md`) to point span at the slot.
Cross-link the layout pages. Update `CHANGELOG` and this PRD's parent §8.7 note.

## 10. Implementation milestones

1. **Configuration + slot** — `DashboardConfiguration`, `WidgetSlot` (+ template),
   `Dashboard::dashboard()` replacing `getWidgets()/getColumns()`; remove
   `Widget::getColumnSpan()` and host root span; render the tree via the shared
   renderer. Migrate fixtures. Tests + gates.
2. **Playground + docs** — rebuild the Insights dashboard with a Section/Grid
   layout; update the widgets docs, CHANGELOG and PRD pointer; browser-verify.

## 11. Acceptance criteria

- A dashboard can arrange widgets with `Section`/`Grid`/`Fieldset`/`Flex` via
  `dashboard(DashboardConfiguration $d)`, and the flat `widgets([...])` path still
  works.
- A `WidgetSlot` spans like a field (`columnSpan(1)` is half in a 2-col grid); a
  non-widget class throws at `make()`.
- Each widget remains an independent Live Component (refresh/poll/`canView()`)
  inside the static layout.
- `composer test && composer phpstan && composer cs` are green; the playground
  Insights dashboard renders the nested layout with no console errors; docs and
  CHANGELOG updated.
