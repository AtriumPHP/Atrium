# Atrium — Product Requirements & Technical Specification

> **Name / namespace:** **Atrium** — Packagist vendor `atrium`, metapackage
> `atriumphp/atrium`, PHP namespace `Atrium\`, bundle class `AtriumBundle`. The
> Packagist `atrium` vendor namespace was unclaimed at time of writing (only an
> abandoned, unrelated `nateritter/atrium-php` exists); register the vendor early
> to lock it in, and verify the npm org + a `.dev` domain before launch.
>
> **Audience:** this document is written to be handed to **Claude Code** as the
> implementation brief. It defines what to build, the constraints, and a phased
> plan with acceptance criteria so work can proceed milestone by milestone.

---

## 1. Summary

A PHP-configured, modular, open-source admin panel framework for Symfony, built for the Symfony
ecosystem. Developers describe an admin interface entirely in PHP (resources,
columns, fields, actions) and get a polished, reactive UI with **no JavaScript
build step and no separate API**. Reactivity is delivered server-side via
**Symfony UX Live Components**; styling via **Tailwind**. The framework is
distributed as composable packages from a monorepo, published to Packagist
under the **MIT** license.

A working **Phase 0** vertical slice already exists (single bundle: panel shell +
reactive list table over a Doctrine entity). This spec extends it into the full
framework.

## 2. Goals

- **PHP-first DX.** A developer configures everything in PHP. Defining a resource
  should take minutes and require no service registration, YAML, or JS.
- **Reactive without a SPA.** Search, sort, filter, paginate, and dynamic forms
  work via server round-trips (Live Components), morphed into the DOM.
- **Modular.** Each capability (tables, forms, actions, etc.) is an independently
  installable Composer package; each works standalone outside the panel.
- **Backend-agnostic data layer.** Tables and forms talk to a `DataProvider`
  abstraction; Doctrine ORM is the default, but API Platform / array / ODM
  adapters must be possible without touching the UI.
- **Open-source ready.** MIT, semantic versioning with a documented BC promise,
  full CI + static analysis + tests, and a docs site.
- **Extensible.** A plugin contract lets third parties register resources, pages,
  widgets, and assets.

## 3. Non-goals

- **Not a React/SPA admin.** No client-side framework, no required JSON API.
  (React "islands" via UX React may be supported later for specific widgets, but
  are out of scope for v1.)
- **Not a CMS or e-commerce platform.** It is an admin-panel *framework*, not a
  finished product like Sulu or Sylius.
- **Not locked to one entity manager.** Doctrine ORM is the default adapter, not
  a hard dependency of the core abstractions.
- **No bespoke design system in v1.** Tailwind utility classes only; a theming
  layer comes later.
- **No multi-tenancy in v1** (planned, but not a v1 milestone).

## 4. Target users & primary use cases

- Symfony developers building internal back offices / admin dashboards over
  existing Doctrine entities.
- Teams wanting a faster, more reactive alternative to hand-rolled CRUD or to the
  config-heavy parts of existing bundles.
- Use cases: CRUD over entities, filtered/sorted/paginated lists, create/edit
  forms with conditional fields, row + bulk actions, dashboard widgets.

## 5. Architecture

### 5.1 Layered model

```
Community plugin packages           (third-party extensions)
        │
Panel builder                        (shell: nav, auth, routing, theme, dashboard)
        │  composes ▼
Composable builder packages          (each installable standalone)
  Forms · Tables · Actions · Infolists · Notifications · Widgets
        │  built on ▼
Symfony foundation
  Live Components · Twig Components · Security voters · DataProvider (Doctrine) ·
  AssetMapper + Tailwind · Notifier
```

Dependency direction is strictly downward. The Panel depends on the builders; the
builders depend only on the foundation and on shared contracts — **never
sideways** between builders. Enforce this; circular package deps are the main
risk to the monorepo split.

### 5.2 Reactivity model

Every interactive surface (table, form, widget) is a Live Component. User
interaction triggers an Ajax round-trip that re-renders the component on the
server; the response is morphed into the DOM, preserving focus and scroll. State
lives entirely in `#[LiveProp]` properties (writable ones for user-controlled
state). No custom JavaScript is written by the framework consumer.

### 5.3 Data abstraction

All reads go through `DataProviderInterface`. The default `DoctrineDataProvider`
builds queries from a `QueryBuilder`. Field names used in DQL come from
developer-supplied config (trusted); user-supplied values (e.g. search terms) are
always bound as parameters. Swapping the backend is a single alias change.

### 5.4 Plugin model

A `PluginInterface` lets a package register resources, pages, widgets, nav
entries, and front-end assets into a panel. Discovery uses bundle
autoconfiguration + tagged services + a compiler pass / registry.

## 6. Technical stack & constraints

| Concern | Choice |
|---|---|
| Language | PHP 8.2+ (target 8.4 for the UX 3.x track) |
| Framework | Symfony 7.x (FrameworkBundle, TwigBundle) |
| Reactivity | `symfony/ux-live-component` + `symfony/ux-twig-component` (^2.13; document a 3.x track — UX 3.0 requires PHP 8.4 / Symfony 7.4 and drops CSRF tokens for same-origin/CORS) |
| ORM | `doctrine/orm` ^3 + `doctrine/doctrine-bundle` ^2 (default adapter only) |
| Assets/CSS | AssetMapper + `symfonycasts/tailwind-bundle` (no Node required); Encore/Vite supported but not required |
| Property access | `symfony/property-access` |
| Authorization | Symfony Security voters / `#[IsGranted]` |
| License | MIT |

**Constraints**

- No browser storage assumptions; no required Node build for consumers.
- Core abstractions (`Resource`, `Column`, `Field`, `DataProviderInterface`) must
  not reference Doctrine types directly.
- Public API changes follow semver + a written BC promise (model on Symfony's).

## 7. Package / monorepo structure

Develop in one monorepo; split into read-only subtree repos with
`symplify/monorepo-builder`; publish each to Packagist.

| Package | Depends on | Purpose |
|---|---|---|
| `atriumphp/admin-core` | — | Contracts, `Resource`, `DataProviderInterface`, registry, base value objects |
| `atriumphp/admin-doctrine` | core | `DoctrineDataProvider` |
| `atriumphp/admin-tables` | core | Table builder + `DataTable` Live Component |
| `atriumphp/admin-forms` | core | Field schema + `Form` Live Component |
| `atriumphp/admin-actions` | core, forms | Row/bulk/page actions + modals |
| `atriumphp/admin-infolists` | core | Read-only record views |
| `atriumphp/admin-notifications` | core | Toasts / flash / persisted notifications |
| `atriumphp/admin-widgets` | core | Dashboard stat cards + charts |
| `atriumphp/admin-panel` | all of the above | The shell that composes everything |
| `atriumphp/atrium` | panel | Metapackage that requires the full set |

> Until the seams are stable, it is acceptable to develop as the single
> `atriumphp/atrium` bundle (Phase 0 state) and extract packages in Phase 7. Design the
> namespaces now so extraction is mechanical.

## 8. Functional requirements

IDs are referenceable from commits/PRs.

### 8.1 Resource API (core) — `RES`

- **RES-01** A resource is a class extending `Atrium\Resource\AdminResource`.
- **RES-02** Resources are auto-discovered via autoconfiguration; no manual
  registration, tags, or YAML.
- **RES-03** A resource declares its entity class, list columns, form fields,
  actions, and (optionally) slug/label/icon/nav group.
- **RES-04** `ResourceRegistry` indexes resources by slug and by class and is the
  single lookup point for panel + components.
- **RES-05** The resource is a **thin coordinator**. For non-trivial resources,
  its table/form/action config is delegated to dedicated classes under a
  per-resource directory (`Tables/`, `Schemas/`, `Pages/`) — see §8.11. Inlining
  `columns()` / `form()` directly on the resource MUST remain valid for small
  cases.
- **RES-06** A resource declares which **pages** it exposes via `pages()`,
  mapping action keys (`index`, `create`, `edit`, custom) to Page classes.

### 8.11 Resource layout & Pages — `LAY`

A per-resource organization, adapted to Symfony's HTTP model.

- **LAY-01** Recommended layout per resource (scaffolded by the maker, `RES`/`LAY`
  do not *require* the user to follow it but the framework defaults to it):

  ```
  src/Admin/Customers/
  ├── CustomerResource.php        # thin coordinator
  ├── Pages/
  │   ├── ListCustomers.php
  │   ├── CreateCustomer.php
  │   └── EditCustomer.php
  ├── Schemas/
  │   └── CustomerForm.php        # static configure(Schema): Schema
  └── Tables/
      └── CustomersTable.php      # static configure(Table): Table
  ```

- **LAY-02** `Tables/*` and `Schemas/*` are **pure config classes** with static
  `configure()` methods, reusable (the same form schema feeds both create + edit)
  and unit-testable in isolation.
- **LAY-03** **A Page is a controller/descriptor class, NOT a Live Component.**
  Symfony UX Live Components are *embedded* in a host template; they are not route
  handlers. A Page owns its route, page type (list/create/edit), header actions,
  and lifecycle hooks (e.g. `mutateDataBeforeSave()`, `getRedirectUrl()`). The
  reactive widgets *inside* the page (the table, the form) are the Live
  Components.
- **LAY-04** The framework provides base Page classes — `ListPage`, `CreatePage`,
  `EditPage` — holding shared controller logic. A resource's Page subclass
  customizes hooks/actions only.
- **LAY-05** The panel registers a small set of **parametric routes**
  (`/admin/{resource}`, `/admin/{resource}/new`, `/admin/{resource}/{id}/edit`); a
  generic dispatcher resolves resource + action → Page class → mounts the right
  Live Component. Adding a CRUD resource therefore needs **no route
  registration**. Only genuinely custom (non-CRUD) pages declare their own route.

### 8.2 Tables — `TBL`

- **TBL-01** List view rendered by a `DataTable` Live Component.
- **TBL-02** Columns via fluent builder: `Column::make('name')->label()->sortable()->searchable()`.
- **TBL-03** Debounced server-side search across `searchable()` columns.
- **TBL-04** Click-to-sort on `sortable()` columns (toggle asc/desc).
- **TBL-05** Pagination with configurable `perPage`.
- **TBL-06** Current search bound to the URL (`LiveProp(url: true)`) for
  bookmarkable views.
- **TBL-07** Column value formatting for scalars, `DateTimeInterface`,
  `BackedEnum`, bool, null, arrays; custom formatter callback support.
- **TBL-08** Empty + loading states.
- **TBL-09** (Stretch) per-column filters and relation columns (with required
  joins handled in the Doctrine adapter).

### 8.3 Forms — `FRM`

- **FRM-01** A field schema mirroring the column API:
  `TextField::make('email')->label()->required()->rules([...])`.
- **FRM-02** Field types for v1: text, textarea, number, select (incl. entity
  select), checkbox/boolean, date/datetime.
- **FRM-03** Create + edit rendered by a `Form` Live Component over a Doctrine
  entity, with hydration/dehydration handled via LiveProps.
- **FRM-04** Validation surfaced inline using Symfony constraints; invalid input
  must not break the component (keep last valid value, expose errors to template).
- **FRM-05** Reactive fields: `->live()` fields re-render dependents on change
  (e.g. dependent select). Implement via writable LiveProps + `onUpdated` hooks.
- **FRM-06** Save persists via the data layer and emits a success notification.
- **FRM-07** Field-level authorization (hide/disable based on voters).

> **Forms v2 (proposed):** the schema-tree + layout (`Grid`/`Section`/`Fieldset`,
> `columnSpan`), conditional visibility (`FRM-08..09`), cross-field reactivity
> (`FRM-10..11`), validation DX (`FRM-12`), and additional field types (`FLD-*`)
> are specified in the addendum
> [`docs/PRDs/PRD-forms-schema-layout.md`](./PRD-forms-schema-layout.md). Slots in as
> Phase 2.5.

### 8.4 Actions — `ACT`

- **ACT-01** Row actions, bulk actions, and page/header actions.
- **ACT-02** Action = label + icon + optional confirmation modal + handler.
- **ACT-03** Built-ins: create, edit, delete (with confirm), bulk delete.
- **ACT-04** Custom actions that open a modal form (reuse Forms) and run a handler.
- **ACT-05** Per-action authorization via voters.

### 8.5 Infolists — `INF`

- **INF-01** Read-only record view built from an entry schema (mirrors fields).
- **INF-02** Layout primitives: sections, key/value entries, badges.

### 8.6 Notifications — `NTF`

- **NTF-01** Toast notifications triggered from components/actions.
- **NTF-02** Flash-message bridge.
- **NTF-03** (Stretch) persisted notifications with an unread indicator.

### 8.7 Widgets — `WGT`

- **WGT-01** Dashboard widgets: stat cards and charts (UX Chart.js).
- **WGT-02** Widgets are Live Components and can refresh independently.

> **Implemented & extended.** The widget and dashboard layer (stat/chart widgets,
> the generic host, multiple routable dashboards, the `/admin/{slug}` namespace
> generalisation) is scoped and delivered in
> [`docs/PRDs/PRD-dashboards-widgets.md`](./PRD-dashboards-widgets.md) (WGT-03..10,
> DSH-01..07, PNL-06..08).

### 8.8 Panel shell — `PNL`

- **PNL-01** Routes: dashboard (`/admin`) + per-resource list/create/edit.
- **PNL-02** Sidebar navigation generated from registered resources (+ nav groups).
- **PNL-03** Tailwind base layout; light/dark friendly.
- **PNL-04** Panel-level configuration object (brand, nav groups, path prefix).
- **PNL-05** Authentication entry point + access control on the panel.

### 8.9 Data providers — `DAT`

- **DAT-01** `DataProviderInterface` with `fetch()` + `count()` (already defined).
- **DAT-02** Default `DoctrineDataProvider`; selection via alias.
- **DAT-03** Persistence operations (create/update/delete) abstracted behind a
  writer interface so non-Doctrine backends are possible.

### 8.10 Authorization — `AUT`

- **AUT-01** Resources, actions, and fields gated via Symfony voters.
- **AUT-02** Sensible default: deny when no voter grants access (configurable).

## 9. Developer-facing API contract (DX spec)

The framework's "configure everything in PHP" promise is its primary product
surface. Two valid shapes: **(a) inline** for small resources, **(b) the
multi-class layout** (the default the maker scaffolds) for everything else. Both
must be supported.

### 9a. Inline (small resources)

```php
final class TagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function columns(): array
    {
        return [
            Column::make('name')->sortable()->searchable(),
            Column::make('slug'),
        ];
    }
}
```

### 9b. Multi-class layout (default for non-trivial resources)

The resource is a thin coordinator; config lives in dedicated, reusable classes
(see §8.11 `LAY`).

```php
// src/Admin/Customers/CustomerResource.php — thin coordinator
final class CustomerResource extends AdminResource
{
    public function getEntityClass(): string { return Customer::class; }
    public function getNavigationIcon(): string { return 'users'; }

    public function table(Table $table): Table  { return CustomersTable::configure($table); }
    public function form(Schema $schema): Schema { return CustomerForm::configure($schema); }

    public static function pages(): array
    {
        return [
            'index'  => ListCustomers::class,
            'create' => CreateCustomer::class,
            'edit'   => EditCustomer::class,
        ];
    }
}
```

```php
// src/Admin/Customers/Tables/CustomersTable.php — pure config, reusable, testable
final class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            Column::make('name')->sortable()->searchable(),
            Column::make('email')->searchable(),
            Column::make('tier')->sortable(),
            Column::make('createdAt')->label('Joined')->sortable(),
        ]);
    }
}
```

```php
// src/Admin/Customers/Schemas/CustomerForm.php — one schema, shared by Create + Edit
final class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->fields([
            TextField::make('name')->required(),
            TextField::make('email')->required()->email(),
            SelectField::make('tier')->options(/* ... */)->live(),
            CheckboxField::make('active'),
        ]);
    }
}
```

```php
// src/Admin/Customers/Pages/ListCustomers.php — route brain, NOT a Live Component
final class ListCustomers extends ListPage
{
    protected static string $resource = CustomerResource::class;

    public function headerActions(): array
    {
        return [CreateAction::make()];
    }
}
```

Stability of these signatures matters; treat changes to them as BC-relevant.

## 10. Implementation phases

Each phase is a milestone Claude Code can complete and verify before moving on.
**Definition of done per phase:** code + tests pass, PHPStan clean at the target
level, CS clean, docs/README updated, acceptance criteria met.

### Phase 0 — Vertical slice *(already built)*
Single `atriumphp/atrium` bundle: panel shell, `AdminResource`, `Column`,
`DataTable` Live Component (search/sort/paginate), `DataProviderInterface` +
`DoctrineDataProvider`, autoconfiguration-based discovery.
*Acceptance:* visiting `/admin` over a sample entity yields a working reactive
table.

### Phase 1 — Engineering harness
Add the quality gates around the existing slice **before** adding features.
*Deliverables:* PHPUnit setup + tests for `Column`, `ResourceRegistry`,
`DoctrineDataProvider` (search/sort/paginate/count), and a functional test that
boots a kernel and renders the table; PHPStan (max) + baseline; PHP-CS-Fixer
(Symfony ruleset); GitHub Actions matrix (PHP 8.2–8.4 × Symfony 7.x stable/LTS);
`CONTRIBUTING.md`, code of conduct, issue/PR templates; `CHANGELOG.md`.
*Acceptance:* CI is green on a fresh clone; coverage exists for the data layer.

### Phase 2 — Forms package + resource layout & Pages
Implement `FRM-01..07` and `LAY-01..05`. Field schema (`Schema` + `*Field`
builders), `Form` Live Component, the `ListPage`/`CreatePage`/`EditPage` base
classes and the parametric-route dispatcher, create/edit pages, validation, one
reactive (`->live()`) dependent field demonstrated, persistence via a writer
interface (`DAT-03`), success notification stub. Add a `make:atrium:resource`
maker that scaffolds the §9b multi-class layout (`Pages/`, `Schemas/`, `Tables/`).
*Acceptance:* `make:atrium:resource Customer` generates the layout; create + edit
of a sample entity works end-to-end with inline validation and a working
dependent select; a custom Page hook (e.g. `getRedirectUrl()`) takes effect.

### Phase 3 — Actions
Implement `ACT-01..05`. Row/bulk/page actions, confirmation modals, built-in
edit/delete/bulk-delete, custom modal-form action.
*Acceptance:* delete-with-confirm and a custom modal action work from the table.

### Phase 4 — Authorization
Implement `AUT-01..02` and the per-resource/action/field gates (`FRM-07`,
`ACT-05`, `PNL-05`).
*Acceptance:* an unauthorized user cannot see/trigger gated resources or actions.

### Phase 5 — Infolists & Widgets
Implement `INF-*` and `WGT-*`. Read-only record view; dashboard with at least one
stat card and one chart widget.
*Acceptance:* a resource exposes a view page; the dashboard renders widgets.

### Phase 6 — Notifications & Plugin system
Implement `NTF-*` and the `PluginInterface` + discovery. Provide one example
plugin that registers a resource and a widget.
*Acceptance:* toasts fire from actions; the example plugin loads with only a
`composer require` + its own autoconfiguration.

### Phase 7 — Monorepo split, docs, release
Extract packages per §7 with `symplify/monorepo-builder`; verify each is
independently installable; stand up a docs site (static, e.g. MkDocs/VitePress);
tag `0.1.0`; document the BC promise; set up Packagist + funding metadata.
*Acceptance:* `composer require atriumphp/admin-tables` works alone; docs cover
install + the Resource API; a clean app can build a panel from the published
packages.

## 11. Engineering & quality requirements

- **Tests:** PHPUnit; unit tests for value objects + data layer; functional tests
  booting a Symfony test kernel for components and panel routes. Aim for
  meaningful coverage of `core` and `doctrine`.
- **Static analysis:** PHPStan at max (with a tracked baseline); optionally Psalm.
- **Coding standard:** PHP-CS-Fixer with the Symfony ruleset; `declare(strict_types=1)`
  everywhere; CI fails on violations.
- **CI:** GitHub Actions matrix across supported PHP × Symfony versions; run
  tests, PHPStan, CS, and a `composer validate`.
- **Versioning:** semantic versioning; written backwards-compatibility promise;
  `@internal` on anything not part of the public API.
- **Docs:** every public API documented; a docs site by Phase 7.

## 12. Open-source hygiene

- `LICENSE` (MIT) in every package.
- `README.md` per package + a root README.
- `CONTRIBUTING.md`, code of conduct, issue/PR templates.
- Unique Packagist vendor namespace; **do not** name it after another admin-panel framework and
  avoid implying official Symfony endorsement (follow Symfony's trademark
  guidelines; verify before finalizing branding). *(This is a flag to check, not
  legal advice.)*
- Decide a sustainability path (GitHub Sponsors / Open Collective) and document
  support boundaries before promoting the project.

## 13. Overall acceptance criteria

The framework is "v1-ready" when, from a clean Symfony 7 app, a developer can:
`composer require atriumphp/atrium`, run Tailwind, define a `Resource` over a Doctrine
entity in PHP only, and get a panel with a reactive list (search/sort/paginate),
create/edit forms with validation and a reactive field, row + bulk actions with
confirmation, voter-based access control, and a dashboard widget — **with no
JavaScript and no separate API** — and each capability is installable as its own
Packagist package.

## 14. Working with this spec in Claude Code

- Keep this file in the repo (e.g. `docs/PRDs/PRD.md`) and reference it per phase.
- Maintain a `CLAUDE.md` at the repo root with conventions (namespaces, the
  downward-dependency rule, "no Doctrine types in core", CS/PHPStan commands).
- Implement **one phase per working session**; run the Phase-1 gates (tests,
  PHPStan, CS) after each, and update `CHANGELOG.md`.
- Treat §9 (the Resource API) as a stable contract; flag any change to it.

---

### Appendix A — current directory layout (Phase 0)

```
atrium/
├── composer.json
├── config/services.php
├── src/
│   ├── AtriumBundle.php
│   ├── Controller/AdminController.php
│   ├── Resource/{AdminResource.php, ResourceRegistry.php}
│   ├── Table/Column.php
│   ├── DataProvider/{DataProviderInterface.php, DoctrineDataProvider.php}
│   └── Twig/Components/DataTable.php
└── templates/
    ├── admin/{layout.html.twig, resource.html.twig}
    └── components/data_table.html.twig
```

### Appendix B — core interfaces to preserve

- `Atrium\Resource\AdminResource` (abstract base; §9 signature)
- `Atrium\DataProvider\DataProviderInterface` (`fetch`, `count`; add a writer
  interface in Phase 2)
- `Atrium\Table\Column` (fluent value object)
- `Atrium\Resource\ResourceRegistry` (slug + class lookup)
