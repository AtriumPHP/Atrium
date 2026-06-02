# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Action lifecycle hooks** on `AdminResource`: `beforeAction`/`afterAction(string
  $action, object $record)` bracket **every** row action's handler, and
  `beforeBulkAction`/`afterBulkAction(string $action, array $records)` bracket a
  bulk action's handler (records pre-filtered to those the user may act on). They
  run inside the action's transaction. `beforeDelete`/`afterDelete` remain the
  delete-specific convenience. **Public Resource API addition.**
- **Form validation lifecycle hooks** on `AdminResource`:
  `mutateFormDataBeforeValidate($data, $operation)` (clean raw input before
  validation) and `afterValidate($data, $operation)` (react to valid data,
  side-effect only — skipped on an invalid submit). **Public Resource API
  addition.**
- **Custom persistence + atomic saves.** `AdminResource::handleRecordCreation()`
  and `handleRecordUpdate(object $record, DataWriterInterface $writer)` own the
  write (defaulting to the data writer), so an app can persist through its own
  service/command bus without replacing the form. The save path now runs
  `beforeSave → handle* → afterSave` inside a single transaction via the new
  `DataWriterInterface::transactional(callable): mixed`, so a failing `afterSave`
  rolls the write back; deletes (record + bulk) are wrapped the same way. The
  Doctrine writer uses `wrapInTransaction`; the array writer just runs the work.
  **Public Resource API + `DataWriterInterface` addition.**
- **Query scoping** via `AdminResource::scopeQuery(DataQuery): DataQuery` (default
  no-op). Returns a query narrowed to the records the resource exposes
  (multi-tenancy, ownership, soft-deletes) using `DataQuery::withFilters([...])`.
  The scope is applied to the list, the count, select-all bulk actions, **and**
  single-record resolution — so an out-of-scope id resolves to `null` (the edit
  page 404s; a forged action finds nothing). Unlike the authorization hooks
  (which hide actions on a still-visible row), scoping removes rows entirely.
  Docs: `docs/integration-guide/data/query-scoping.md`. **Public Resource API
  addition.**

- **Authorization hooks** on `AdminResource` (`canViewAny`, `canCreate`,
  `canEdit`, `canDelete`, `canView`, dispatched via `can()`). Enforced server-side
  in three places: the controller returns **403** for a denied list/create/edit
  page; the form save re-checks `canCreate`/`canEdit`; and the table hides and
  refuses to run the built-in Edit/Delete/New actions (a bulk delete acts only on
  permitted records). Custom actions opt in with `Action::authorize('ability')`;
  the built-in table actions set theirs automatically. Default is open
  (security-agnostic core). **Public Resource API addition.**
- **Record lifecycle hooks** on `AdminResource`: `mutateFormDataBeforeFill`,
  `mutateFormDataBeforeSave($data, $operation)`, `beforeSave`/`afterSave($record,
  $operation)`, and `beforeDelete`/`afterDelete($record)`. The save path runs
  mutate → apply fields → `beforeSave` → persist → `afterSave`; delete runs
  `beforeDelete`/`afterDelete` around the built-in delete actions. **Public
  Resource API addition.**
- Integration guide: `docs/integration-guide/resources/authorization.md` and
  `lifecycle-hooks.md` (and a documentation policy + format — see
  `docs/integration-guide/README.md`).

### Changed

- **`mutateFormDataBeforeFill()` gained an `$operation` parameter and now runs on
  create too.** Signature is now `mutateFormDataBeforeFill(array $data, string
  $operation): array`; on `create` it receives the fields' defaults, so it can
  seed a create form (previously it ran on edit only). Overriders must add the
  parameter. **Public Resource API change** (pre-1.0; the hook was added earlier
  in this same unreleased cycle).
- **`DataProviderInterface::find()` gained an optional `array $filters = []`**
  parameter so record resolution can be scoped (see query scoping above). Callers
  are unaffected; custom data-provider implementations must add the parameter.
  Both built-in providers (Doctrine, array) honour it. **Public contract change.**
- **Table configuration is now a single `table(TableConfiguration $table)` hook**
  on `AdminResource`, mirroring `form(Schema $schema): Schema`. It replaces the
  separate `columns()` / `recordActions()` / `headerActions()` / `bulkActions()`
  methods — set them all on the `Atrium\Table\TableConfiguration` builder
  (`->columns([…])->recordActions([…])->bulkActions([…])`). A fresh
  `TableConfiguration` already carries the framework defaults (an Edit record
  action and a "New" header action), so overriding `table()` keeps them unless a
  setter overrides them. **Public API change** (pre-1.0; the four methods were
  added earlier in this same unreleased cycle).

### Added

- Table **filters** (TBL-11): `TableConfiguration::filters([...])` renders a filter
  bar that narrows the query through the data provider. `SelectFilter` (a
  categorical dropdown) and `TernaryFilter` (all / true / false over a boolean
  field), both extending `Atrium\Table\Filter\Filter` and declaring their own
  template for extensibility. Filters resolve to equality conditions carried on
  `DataQuery::$filters` and applied by both `ArrayDataProvider` and
  `DoctrineDataProvider` (parameter-bound). Selections live in a writable
  `filterValues` LiveProp (with a Reset action); a value for an unconfigured
  filter name is ignored, so a forged value cannot inject a condition.
- **Column presentation** options on `Column`: `->alignment('right')`
  (`alignCenter()` / `alignRight()`), `->width('8rem')`, `->boolean()` (renders a
  check/cross icon instead of "Yes"/"No"), `->badge()` with `->color('green')` or
  a per-value `->color(fn ($value, $record) => …)`, and `->visible(false)` /
  `->hidden()` to drop a column from the header, cells, search and sort. Cells are
  now resolved to render-ready descriptors (`Column::toCell()`); `renderValue()`
  is unchanged for direct callers.
- Table **empty state** on `TableConfiguration`:
  `->emptyState('No articles yet', 'Write your first one…', 'document')` renders an
  icon, heading and optional description when the table has no rows, instead of the
  bare "No … found." default (which still applies when unconfigured).
- Table-level **default sort** and **configurable page size** on
  `TableConfiguration`: `->defaultSort('field', 'desc')` orders the first load
  (until the user sorts; the field need not be a sortable column), and
  `->paginated(25, [10, 25, 50])` sets the page size and, given options, renders a
  per-page selector. A client-supplied page size is clamped to a configured
  choice, so a forged value cannot request an arbitrarily large page.
- Table **header actions** and **bulk / row-selection actions** (TBL-09, TBL-10),
  built on the existing `Atrium\Action` subsystem.
  - Configured via `TableConfiguration::headerActions()` (defaults to a
    `CreateAction`) and `bulkActions()` (defaults to none — returning actions
    enables row selection). Both are subject-less `list<Action>`.
  - Built-in `Atrium\Table\Action\CreateAction` (a primary "New" button linking
    to the create page) and `BulkDeleteAction` (a confirmed server action that
    deletes the whole selection through `DataWriterInterface`). Header actions now
    render inside the `DataTable` Live Component (its card header), not the page
    chrome, so server-driven header actions work; the hardcoded "New" button was
    removed from the resource page template.
  - `Atrium\Action\Concern\InteractsWithBulkActions` — a reusable trait owning a
    server-driven selection state machine: per-row and whole-page toggles, plus a
    **select-all-matching-the-query** mode (a flag with an exclusion list) so a
    bulk action targets every record across all pages, not just the visible ones.
    Confirmable `requestBulkAction` / `confirmBulkAction`, gated by visibility on
    both request and run. The selection props are non-writable LiveProps (mutated
    only through the actions), so a crafted request cannot forge a selection.
  - `Atrium\Action\Action` gained subject-less rendering (`toStandaloneView()`,
    `getStandaloneUrl()`) and `isVisible()` for header/bulk bars — which requires
    a plain bool and **fails closed** for a subject-bound visibility closure, so a
    destructive action a developer tried to gate with a closure is never silently
    exposed. The action button template is parameterised (`liveAction`) and the
    confirmation dialog was extracted to a shared `components/confirm_modal.html.twig`.
  - Known limitation: a select-all bulk action loads every matching record in one
    request; batched / queued execution for very large selections is deferred.
- Table **record actions** + a generic, view-agnostic **`Atrium\Action`**
  subsystem (the shared base for table actions today; header/page/bulk actions
  next).
  - `Atrium\Action\Action` — a fluent action (`label`/`icon`/`color`,
    `button()`/`link()`/`iconButton()` styles, `badge()`, `visible()`/`hidden()`,
    `requiresConfirmation()`); it is either a **link** (`url()`) or a **server
    action** (`action(Closure)`). `Atrium\Action\ActionGroup` renders a set as a
    no-JS `<details>` dropdown. Both implement `ActionContract` so a host renders
    and runs them polymorphically, and each declares its own `getTemplate()` so
    custom actions/groups can ship their own renderer.
  - Built-in table actions `Atrium\Table\Action\EditAction` (link to the edit
    page) and `DeleteAction` (a confirmed server action that deletes through
    `DataWriterInterface`). `AdminResource::recordActions()` declares them
    (defaults to Edit); a resource picks any mix of links, server actions and
    groups.
  - `DataTable` renders the actions column and runs server actions via the
    reusable `Atrium\Action\Concern\InteractsWithActions` trait: a **server-driven
    confirmation** (no client JavaScript) gated by visibility on both request and
    run, so a crafted request can neither surface nor execute a hidden action.
    Rendering lives in reusable `components/action{,s,_group}.html.twig` partials.
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
- Forms v2 / M5 — **Wizard** (`SCH-10`). `Atrium\Layout\Wizard` + `Atrium\Layout\Step`,
  a multi-step container, plus a `WizardForm` Live Component.
  - `WizardForm extends Form`: the wizard interaction (Next/Back, gated
    advancement, a header that jumps backwards) is layered on the unchanged
    hydrate/validate/save core. `Form` was made extensible for this — non-`final`,
    with `protected` `collectErrors()`/`fieldsIn()`/`schema()` and a
    `focusContainer()` hook the subclass overrides to focus an errored step.
  - The current step is **server state** (`currentSteps` prop); **Next**
    validates only the current step's fields before advancing, **Back** is free,
    and the wizard owns submit (Submit appears on the last step). All panels
    render (inactive `hidden`), so navigating keeps in-progress input.
  - A resource selects its form component via the new
    `AdminResource::getFormComponentName()` — `Atrium:Form` by default, upgrading
    to `Atrium:WizardForm` when the schema contains a `Wizard`. The form page
    embeds it dynamically (`{{ component(resource.formComponentName, …) }}`).
  - `Step::make('Label')->icon()->description()->columns()->schema([…])`.
- Forms v2 / M5 — **Tabs** (`SCH-10`). `Atrium\Layout\Tabs` + `Atrium\Layout\Tab`,
  a tabbed container on the same schema tree.
  - The active tab is **server state** on the host Live Component (a `selectTab`
    live action + an `activeTabs` prop), so it survives unrelated re-renders and
    needs no client JavaScript. Every panel is rendered each request with
    inactive ones `hidden`, so switching only flips an attribute — inputs in
    other tabs stay in the DOM and keep their in-progress values across the
    morph. A failed save focuses the first tab holding a validation error.
  - `Tab::make('Label')->icon(…)->badge(…)->columns(…)->schema([…])`; `Tabs` has a
    stable id (auto-derived from its tabs, or `->id()`) used to key the state.
- Forms v2 / M5 (cont.) — cross-field validation, inline labels and the
  add/remove row editors that complete Tags / Key-value (`FRM-12`, `FRM-13`,
  `FLD-06`, `FLD-07`).
  - **`->same(field)` / `->different(field)`** (`FRM-12`) — cross-field comparison
    rules the `Form` evaluates against the full submitted state (a Symfony
    constraint can't see a sibling field); each takes an optional custom message.
  - **`->inlineLabel()`** (`FRM-13`) — render a field's label beside the input in a
    responsive column instead of above it.
  - **Tags / Key-value are now full row editors** (`FLD-06`/`FLD-07`): one input
    per tag (a key + value input per pair), with add/remove handled by generic
    `addRow`/`removeRow` Live Component actions. Fields opt in via the new
    `Atrium\Form\Field\RepeatableField` contract, so the renderer stays generic;
    empty rows are dropped on save by the existing `normalize()`.
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

- UI refresh — a modern, polished look across the panel:
  - New shell: a sticky, backdrop-blurred sidebar with a gradient brand mark, a
    grouped icon navigation (inline-SVG `@Atrium/icon.html.twig` set) and a filled
    active state; a sticky, blurred topbar.
  - Dashboard with icon-tiled resource cards (hover lift) and a glowing welcome
    card; resource list pages gained a "New <singular>" action button.
  - Form sections are now header + divider + padded cards; the form page is a
    centered column (no double-card), with a spinner-backed save state and a check
    icon on the success notice.
  - Data table: search with an inline icon, uppercase column headers, a bold
    first column, row hover, and icon pagination controls.
  - Inputs rounded to `lg` with colour transitions. All accents use `primary`.
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
