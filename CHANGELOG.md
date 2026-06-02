# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Phase 0 boilerplate: installable Symfony 8.1 bundle skeleton.
  - `AtriumBundle` (`AbstractBundle`) with `path_prefix` / `brand` configuration
    and `atrium.resource` autoconfiguration.
  - Core contracts and value objects: `AdminResource`, `ResourceRegistry`,
    `Column`, `DataProviderInterface`, `DoctrineDataProvider` (stub).
  - Engineering harness: PHPUnit, PHPStan (max), PHP-CS-Fixer (Symfony ruleset),
    GitHub Actions CI, and unit tests for `Column` and `ResourceRegistry`.
- Phase 1 engineering harness — data-layer and functional coverage.
  - `DoctrineDataProviderTest` exercising `count()` and `fetch()`
    offset/limit pagination against a real in-memory SQLite `EntityManager`
    (`EntityManagerFactory` + `Product` fixture entity).
  - `KernelBootTest` + `AtriumTestKernel` (MicroKernel): boots
    FrameworkBundle + TwigBundle + AtriumBundle and asserts bundle registration,
    `ResourceRegistry` wiring, autoconfiguration-based resource discovery
    (RES-02), exposed configuration parameters, and `@Atrium` Twig rendering.
  - Added `symfony/var-exporter` (dev) and enabled Doctrine native lazy objects
    in the test EntityManager (PHP 8.4+ requirement under ORM 3.6 / var-exporter 8).
  - Open-source hygiene: `CODE_OF_CONDUCT.md` (Contributor Covenant 2.1), GitHub
    issue templates (bug report, feature request) and a pull request template.
- Reactive panel vertical slice (TBL-01..08, PNL-01..04, DAT-01..02).
  - Backend-agnostic read layer: `DataQuery` value object; `DataProviderInterface`
    now takes a query; `DoctrineDataProvider` builds a parameter-bound
    QueryBuilder (search/sort/paginate/count); new in-memory `ArrayDataProvider`.
  - `Column` value extraction + formatting (scalars, dates, enums, bool, arrays,
    null) with a custom `formatStateUsing()` callback.
  - `DataTable` Live Component (search bound to the URL, click-to-sort,
    pagination, empty/loading states) and its Twig template.
  - Panel shell: `AdminController` with parametric routes (`/admin`,
    `/admin/{resource}`), Tailwind layout with registry-driven sidebar nav,
    dashboard and resource pages. Doctrine wiring activates only when
    DoctrineBundle is present.
  - Self-contained styling: the bundle ships a precompiled Tailwind stylesheet
    (`assets/dist/atrium.css`, built from its own templates via the standalone
    CLI — no Node) exposed through an AssetMapper path and an `atrium_stylesheet()`
    Twig helper, so consumers need no Tailwind configuration. The JS entrypoint
    is rendered via `atrium_importmap()`.
  - Functional tests: panel routes (WebTestCase) and reactive component behaviour
    (search/sort/paginate via `InteractsWithLiveComponents`).
  - Promoted `symfony/ux-live-component`, `symfony/ux-twig-component`,
    `symfony/routing` and `symfony/http-foundation` to runtime requirements.
- Forms, persistence and Pages (FRM-01..06, DAT-03, LAY-03..05).
  - `Schema` + fluent `Field` hierarchy: Text (email/url), Textarea, Number,
    Select (static + `optionsUsing()` for dependent selects), Checkbox, Date,
    DateTime — with normalize/format and Symfony-constraint validation.
  - `DataWriterInterface` (DAT-03) with `DoctrineDataWriter` + in-memory
    `ArrayDataWriter`; `DataProviderInterface::find()` for single-record loads.
  - `Form` Live Component: hydrate/dehydrate via `formData` LiveProp, inline
    validation (keeps last values), reactive `->live()` fields driving dependent
    selects, save through the writer, success notice / redirect. Field rendering
    is split into per-type widget partials under `components/form/widget/`; each
    `Field` declares its widget template via `getTemplate()` (and label placement
    via `rendersOwnLabel()`), so third-party apps can add custom field types that
    ship their own templates from any bundle — no change to the renderer.
  - Page descriptor classes (`Page`, `ListPage`, `CreatePage`, `EditPage`) with a
    `getRedirectUrl()` hook and `PageContext`; `AdminResource::pages()`. Generic
    dispatcher routes — `/admin/{resource}/new` and `/admin/{resource}/{id}/edit`
    — resolve resource + action → Page → embed the Form component, so adding a
    CRUD resource needs no route registration.
  - Added `symfony/validator` as a runtime requirement.
- `docs/custom-fields.md` — documents the custom field type extension point
  (the `getType()` / `getTemplate()` / `rendersOwnLabel()` contract, the widget
  template context, and a worked `CountrySelect` example).
- Forms v2 / M1 — schema tree & layout (`SCH-01..08`). A form `Schema` is now a
  tree of `Atrium\Layout\Component`s (fields + layout containers), not a flat
  list.
  - New top-level **`Atrium\Layout`** subsystem (view-agnostic, reusable by future
    dashboards/infolists): `Component` contract, `LayoutComponent` base,
    `Grid`/`Flex`/`Section`/`Fieldset`, and placement concerns `HasColumnSpan`
    (`columnSpan()`/`columnSpanFull()`) and `HasGrow` (`grow()` for `Flex`
    children; `Flex::from()` sets the row's breakpoint).
  - `Schema::components([...])` builds the tree; `Schema::fields([...])` is kept
    as the flat shortcut. `Schema::getFields()` flattens leaves depth-first for
    hydration/validation; `getComponents()` exposes the tree for rendering.
  - A generic recursive renderer (`components/layout/*.html.twig`) lays nodes out
    in responsive grids; grid utilities are safelisted in `assets/atrium.css`
    (`@source inline(...)`) since they're composed in PHP.
  - Forms v2 PRD addendum updated to mark M1 delivered.
- Forms v2 / M2 — conditional field visibility (`FRM-08`, `FRM-09`) + the `Get`
  state accessor (`FRM-11`).
  - `Atrium\Form\Get` — an invokable read accessor over the form state
    (`$get('field')`), handed to field callbacks.
  - `Atrium\Form\Concern\HasVisibility` on `Field`: `visible(bool|Closure)`,
    `hidden(bool|Closure)`, `visibleOn(op)`, `hiddenOn(op)` — all
    **server-evaluated** during the Live Component re-render (no client logic).
  - The `Form` component filters hidden fields out of the render tree (cloning
    containers, dropping any left empty) and skips them in validation/hydration;
    it exposes `operation()` (`create`/`edit`).
- Forms v2 / M3 — cross-field reactivity, the `Set` accessor, validation DX and
  presentation niceties (`FRM-10..13`).
  - `Atrium\Form\Set` — invokable write accessor over form state (`$set('f', v)`).
  - `Atrium\Form\Concern\HasReactivity` on `Field`: `afterStateUpdated(Closure)`
    (auto-implies `live()`); the callback gets `($state, Get, Set)` and can derive
    one field from another (e.g. SKU from name). The `Form` component diffs
    `formData` against the previous render in a `#[PreReRender]` pass to detect the
    changed field — robust to per-field sub-path model writes.
  - `Atrium\Form\Concern\HasValidationRules` on `Field`: `maxLength()`,
    `minLength()`, `length()`, `regex()` (→ `Length`/`Regex`); `NumberField` gains
    `min()`/`max()` (→ `GreaterThanOrEqual`/`LessThanOrEqual`). `rules([...])`
    stays the escape hatch.
  - Presentation: `placeholder()` (Text/Textarea/Number via `HasPlaceholder`),
    `autofocus()`, `hiddenLabel()` on `Field`; widgets + wrapper updated.
- Forms v2 / M5 (partial) — container visibility, `dehydrated(false)`, and the
  Tags / Key-value fields.
  - **Container-level visibility:** `visible()`/`hidden()`/`visibleOn()`/`hiddenOn()`
    now apply to layout containers too (a hidden `Section`/`Grid`/`Flex`/`Fieldset`
    drops its whole subtree). Visibility moved to
    `Atrium\Layout\Concern\HasVisibility` over a generic `Atrium\Layout\StateAccessor`
    (implemented by `Atrium\Form\Get`), keeping `Atrium\Layout` free of any form
    dependency.
  - **`Field::dehydrated(false)`** (`FRM-14`) — a field shown and validated but not
    written to the model on save.
  - **`TagsField`** (`FLD-06`, `list<string>`) and **`KeyValueField`** (`FLD-07`,
    `array<string,string>`) — zero-JS, server-driven (comma string / `key: value`
    lines) with chip / preview rendering.
- Forms v2 / M4 — new field types (`FLD-01..05`): `RadioField`,
  `ToggleButtonsField` (both extend `SelectField`), `ToggleField` (a switch,
  extends `CheckboxField`), `ColorField`, and `HiddenField`. `Field::rendersInLayout()`
  (default true; false for `HiddenField`) keeps a hidden value in the form state —
  validated and persisted — while drawing no widget and taking no grid cell.
  Widgets under `components/form/widget/{radio,toggle,color,toggle_buttons}.html.twig`.
- Content components (`CNT-01..03`) — static building blocks for a schema
  (`Atrium\Content\Text`, `UnorderedList`, `Image`). They implement the shared
  `Atrium\Layout\Component` contract but carry no form state: never hydrated or
  validated, and skipped by `Schema::getFields()`. `Text` supports semantic
  colour, size, weight, badge and raw-HTML modes. Useful for headings,
  instructions and callouts placed beside fields (with `columnSpan`/`grow`).

### Changed

- Theming: the panel accent is now a single semantic **`primary`** colour
  (default palette "blue-energy"). Templates only use `*-primary-*` classes, so
  re-skinning is one change — edit the eleven `--color-primary-*` values in
  `assets/atrium.css` and `composer build-css`, **or** override `--color-primary-*`
  at runtime in a stylesheet loaded after `atrium.css` (no rebuild; the utilities
  resolve the CSS variables). Replaces the previous hard-coded `indigo` accent.
- Built-in concrete `Field` types (`TextField`, `TextareaField`, `NumberField`,
  `CheckboxField`, `SelectField`, `DateField`, `DateTimeField`) are now
  non-`final` so applications can subclass them to add custom fields; the
  abstract `Field` remains the public contract.
- Form widgets now carry full dark-mode variants — labels, inputs, help/error
  text, the success notice and the checkbox — so forms are legible in dark mode.
- The form page card is now full-width (removed the `max-w-2xl` constraint).
- **BC (pre-release):** `Field::getTemplate()` now returns the field's layout
  wrapper; the input widget moved to the new `Field::getWidgetTemplate()`. Custom
  field types that shipped their own widget should override `getWidgetTemplate()`
  instead of `getTemplate()` (see `docs/custom-fields.md`).
