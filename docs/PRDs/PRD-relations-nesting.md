# PRD — Relation managers & nested resources

> Show and manage a record's **related records**. A resource declares its
> relationships in PHP (`relations()` returning `Relation::make(...)` descriptors);
> each renders as a **relation manager** — an interactive table of related rows with
> its own create/edit/delete and link/unlink actions — embedded on the parent's
> Edit and View screens. For related records that deserve a full-page CRUD
> experience, a resource declares a **parent** (`ParentRelation`) and becomes a
> **nested resource** at `/{parent}/{parentId}/{resource}/...`. Storage stays behind
> a new `RelationDataProvider` seam; core never references Doctrine.

Status: **proposed** · Feature IDs: `REL-01`..`REL-22`

## 1. Motivation

Atrium ships List/Create/Edit/View for a single entity, and **read-only** relation
display (dotted-path `Column`/`Entry`, the `RepeatableEntry`). It has **no way to
manage** a record's relations: you cannot list a course's lessons with their own
actions, add a lesson to a course, attach tags to a post, or detach one. These are
among the most common admin needs — order → line items, post ↔ tags, course →
lessons, user → addresses — and today an integrator must hand-roll a controller,
a scoped table, and bespoke link/unlink logic.

Two complementary shapes cover the space (the same split the researched prior art
draws):

- A **relation manager** — a related-records table embedded on the parent's page,
  for relations simple enough to create/edit inline or in a modal.
- A **nested resource** — a child resource scoped under a parent record with its
  own full pages and hierarchical URLs, for relations complex enough to deserve a
  full-page experience. A nested resource still uses a relation manager on the
  parent as its entry-point list; nesting upgrades that list's row links from
  inline edit to the child's own pages.

The seams are largely in place: `DataTable` is already a Live Component scoped by
`AdminResource::scopeFilters()`; the `Action` subsystem already does
confirmation/authorization/visibility; `Tabs` already gives a server-driven tabbed
container; `getIdentifierField()` (just landed) already resolves a record by a
scoped, possibly non-`id` key — exactly what parent-scoped child lookups need. This
feature adds the **relationship descriptor**, a **shared record-table render core**
extracted from `DataTable`, the **`RelationManager` Live Component**, the
**link/unlink action family**, the **`RelationDataProvider`** storage seam, and the
**nested-resource routing + breadcrumb** layer.

## 2. Goals

- `REL-01` — A resource declares its relationships with **`relations(): array`**,
  each a **`Relation::make('name')`** descriptor (peer to `table()`/`form()`/
  `view()`). Relationships are **declared explicitly in PHP** — kind, target
  resource, and keys — so core needs no Doctrine association metadata.
- `REL-02` — Two relationship **kinds** in v1:
  - **one-to-many** (`->oneToMany(TargetResource::class)->foreignKey('course_id')`)
    — the parent owns many children that carry a foreign key back.
  - **many-to-many** (`->manyToMany(TargetResource::class)->pivotTable('post_tag')
    ->pivotKeys(parent: 'post_id', related: 'tag_id')`) — linked through a pivot/join
    table, optionally with **pivot attributes** (`->pivotColumns(['sort'])`).
- `REL-03` — A **shared record-table render core** is extracted from `DataTable` (an
  abstract base `AbstractRecordTable` + a shared Twig partial) so a related table and
  the list table share *one* presentation contract and HTML, differing only in their
  **data source** and **action set**. (See §5.2 — this is a deliberate, scoped
  refactor of `DataTable`, not a template fork.)
- `REL-04` — Each relation renders as a **relation manager**: a `RelationManager`
  Live Component (it mutates and paginates) extending `AbstractRecordTable`, fed by
  `RelationDataProvider`, mounted with the parent identity + relation name.
- `REL-05` — **Owned-record actions** (both kinds): `CreateAction` / `EditAction` /
  `ViewAction` / `DeleteAction` (+ `BulkDeleteAction`) acting on the related record's
  own lifecycle, scoped to the parent (create sets the link).
- `REL-06` — **Link/unlink actions by kind** — the action taxonomy derives from the
  declared kind:
  - one-to-many → **`AssociateAction` / `DissociateAction`** (set / clear the child's
    foreign key; the child record persists).
  - many-to-many → **`AttachAction` / `DetachAction`** (+ `DetachBulkAction`) (insert
    / remove a pivot row; the related record is untouched).
- `REL-07` — **Associate/Attach pick an _existing_ record** via a `Select` over the
  target resource, titled by **`->recordTitle('name')`**, the candidate list drawn
  from a dedicated **`listLinkable`** data-layer call that **excludes already-linked
  records server-side** and paginates (§5.5). `AttachAction` additionally renders
  **pivot-attribute fields**; the related table shows pivot columns.
- `REL-08` — Relation managers are **placed on the parent's pages** via a small
  **`RelationManagers` host** (§5.4): below the form on **Edit**, and below the
  entries on **View**; multiple relations render as a **server-driven tab strip**
  (the host owns the active-tab state and mounts the selected manager). A single
  relation renders as a titled section.
- `REL-09` — On the **View** screen, a relation manager is **read-only by default**
  (link/unlink/create/edit/delete auto-hidden, leaving View + pagination/search),
  overridable per relation (`->readOnlyOnView(false)`).
- `REL-10` — A relation manager **scopes the related list to the parent record** —
  one-to-many via a foreign-key equality; many-to-many via a pivot join — composed on
  top of the **target resource's own `scopeQuery()`**, which the manager applies to
  the `DataQuery` *before* the data call (§5.5). A relation can never reveal rows the
  target resource would hide.
- `REL-11` — A new storage-agnostic **`RelationDataProvider`** interface (core),
  implemented by the **Doctrine adapter** and the **array adapter**, exposes
  `listRelated`/`countRelated`/`listLinkable`/`countLinkable`/`associate`/`dissociate`/
  `attach`/`detach`. Core passes the descriptor + parent + child; the adapter executes
  the keys/pivot the descriptor names. Owned create/edit/delete reuse the existing
  `DataWriterInterface`; relation writes **participate in the same transaction** as
  those writes (§5.5).
- `REL-12` — **Authorization**: owned create/edit/view/delete gate on the **target
  resource**'s abilities; link/unlink gate on **first-class parent-side hooks** —
  `canAssociate`/`canDissociate`/`canAttach`/`canDetach($parent, $child)` on
  `AdminResource` (default-allow), reachable through `can()`. Denied actions are
  hidden **and** refuse to run (re-checked at execution; Live endpoints are directly
  POST-able). *(Public `AdminResource` API addition — BC-relevant, flagged in the
  CHANGELOG.)*
- `REL-13` — A relation may be **conditionally shown** for a parent record
  (`->visible(fn (object $parent): bool => ...)`).
- `REL-14` — A resource becomes a **nested resource** by declaring a **parent**:
  **`parent(): ?ParentRelation`** returning
  `ParentRelation::make(CourseResource::class)->relationship('lessons')
  ->foreignKey('course_id')`. Its pages then live under the parent record's URL.
- `REL-15` — **Nested routing**: an explicit route family
  `/{parentResource}/{parentId}/{resource}` (+ `/new`, `/{id}`, `/{id}/edit`) with
  exact declaration order + requirements proven not to shadow the flat routes (§5.4).
  The **parent id is resolved scoped** (via the parent resource's
  `getIdentifierField()` + `scopeFilters()`); the child operations carry an
  **additional parent-scope filter** (`foreignKey = parentId`) and **set that key on
  create**; a child outside the parent → 404.
- `REL-16` — **URL generation for nested resources** via a `NestedActionContext`
  (extends `ActionContext` with the parent segment) so row/header actions inside a
  nested resource's tables produce `/{parent}/{parentId}/{resource}/{id}/...` without
  forking the action classes. `PageContext` gains `parentRecords` + `nestedUrl()`.
- `REL-17` — **Breadcrumbs / ancestry**: a `ParentRelation` chain produces a
  breadcrumb trail (`Courses › Intro › Lessons › Variables`), recursive for
  multi-level nesting; rendered from `PageContext.parentRecords`.
- `REL-18` — On a nested resource's **parent**, the relation manager's **row links
  target the child's full nested pages** (view/edit) and its "New" links to the nested
  create page, instead of inline modals — the relation-manager ↔ nested-resource
  contrast.
- `REL-19` — **Inline-first, extractable** (CLAUDE.md rule 6): a relation is
  configured inline on `relations()`, including its `table()`/`form()`; a large
  relation may instead point at a dedicated **`RelationManager` config class** (a
  plain PHP class with `table()`/`form()`, *not* a Live Component), the same way
  `pages()` can be inline or dedicated.
- `REL-20` — **Boot-time validation**: a relation whose target resource is not
  registered, or a nested resource whose `ParentRelation::foreignKey` disagrees with
  the parent relation's `foreignKey`, raises a clear configuration error at boot, not
  a null at runtime.
- `REL-21` — **Docs** (`docs/integration-guide/relations/{overview,nesting}.md`) +
  **playground** (Post → Comments 1:M, Post ↔ Tags M:N, Course → Lessons nested),
  browser-verified; CHANGELOG `REL-01..22`.
- `REL-22` — A **separate code-review agent** reviews the implementation after build
  (process parity with the View feature).

## 3. Non-goals

- **Auto-introspection of relationships from Doctrine.** Declared explicitly in PHP —
  reading association mapping would push relationship knowledge into the adapter and
  break the "no Doctrine in core / no magic" rules. (A future opt-in introspection
  helper is possible but out of scope.)
- **`HasManyThrough` / polymorphic (`MorphMany`/`MorphToMany`).** v1 is one-to-many
  and many-to-many only; the descriptor is shaped to admit more kinds later without a
  redesign (the kind is an enum the action/data layers switch on).
- **Inline repeatable *editing* in the parent form.** Read-only `RepeatableEntry`
  exists (View); an editable repeater field is a separate Forms feature. The relation
  manager is the managed-relationship surface.
- **`Select`/`CheckboxList` form fields backed by a relationship** (choose-existing
  inside the parent form). Useful and complementary, but a Forms-layer feature, not
  the relation *manager*/*nesting* this PRD covers.
- **A maker** (`make:atrium:relation` / `--nested`). Generators are deferred globally.
- **Drag-reordering related records.** The pivot `sort` column is *displayable/
  editable*, not drag-sortable in v1; a sortable pivot is a later candidate.
- **Framework validation of FK nullability for `DissociateAction`.** Whether a child's
  FK may be nulled is schema knowledge that lives in the adapter/DB; the integrator
  enables `DissociateAction` only for nullable FKs. The framework documents this and
  does not introspect it (§5.3).

## 4. Decisions (from brainstorm + PRD self-review)

| Decision | Choice |
| --- | --- |
| Relationship description | **Explicit PHP descriptor** (`Relation::make()` with kind + target resource + keys/pivot). Core stays storage-agnostic; the Doctrine adapter only executes. |
| Kinds in v1 | **one-to-many and many-to-many** (full link/unlink + pivot), staged 1:M then M:N. |
| Scope of this PRD | **Relation managers _and_ nested resources** in one milestoned PRD (nesting builds on managers). |
| Target | A relation points at a **target _resource_** (held as a `class-string`, resolved through `ResourceRegistry` — never `new`'d directly) so the related table reuses the target's columns/form/`getIdentifierField()`/authorization, and nesting can route to its pages. |
| Rendering | **Extract a shared `AbstractRecordTable` core** from `DataTable`; `RelationManager` extends it. They share one presentation contract + Twig partial and differ only in data source (`DataProviderInterface` vs `RelationDataProvider`) and action set. **Not** a template fork, **not** a parent-scope mode bolted onto `DataTable`. |
| Placement | A `RelationManagers` **host** renders the managers below the Edit form / View entries; multiple → a **server-driven tab strip** owned by the host (the existing schema-level `Tabs` is not a page-level widget — see §5.4). Read-only on View by default. |
| Action taxonomy | Derived from the **declared kind**: 1:M → associate/dissociate; M:N → attach/detach (+ pivot fields); both → create/edit/view/delete on the owned record. |
| Storage seam | New **`RelationDataProvider`** core interface (Doctrine + array adapters), including a `listLinkable` for the existing-record picker; relation writes share the `DataWriterInterface` transaction (§5.5). No Doctrine in core (rule 3). |
| Nesting | A child declares **`parent(): ?ParentRelation`**; an explicit **nested route family** scopes operations to the resolved parent; a `NestedActionContext` carries the parent segment; recursive **breadcrumb** chain. |
| Authorization | Owned → target resource abilities; link/unlink → **new first-class `canAssociate`/`canDissociate`/`canAttach`/`canDetach`** hooks on `AdminResource` (BC-relevant). Re-checked at execution. |
| Inline-first | A relation (and its `table()`/`form()`) is configured inline; extractable to a dedicated `RelationManager` config class for large configs (rule 6). |

### Why an explicit descriptor (not introspection)

Filament derives its action taxonomy (attach vs associate vs create) from the
Eloquent relationship *type*. Atrium's core **must not** reference Doctrine
(`ClassMetadata`, association mappings) — rule 3 — and its ethos is PHP-configured,
no-magic. So the **developer declares the kind and keys**; the descriptor is the
single source of truth that drives the manager UI, the action set, the data-layer
calls, and nesting. The Doctrine adapter executes those keys with a query/DML; it
never teaches core about associations, keeping the dependency arrow downward and
making the non-Doctrine array adapter a first-class proof.

## 5. API design

### 5.1 The relationship descriptor — `Atrium\Relation\Relation`

`relations()` is a peer of `table()`/`form()`/`view()`: data-shaped configuration the
resource owns, returning a list of descriptors.

```php
use Atrium\Relation\Relation;

final class PostResource extends AdminResource
{
    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            // one-to-many: Post hasMany Comment (Comment.post_id → Post)
            Relation::make('comments')
                ->oneToMany(CommentResource::class)   // class-string; resolved via ResourceRegistry
                ->foreignKey('post_id')
                ->recordTitle('body')
                ->table(fn (TableConfiguration $t): TableConfiguration => $t->columns([
                    Column::make('body')->limit(60),
                    Column::make('author.name')->label('Author'),
                    Column::make('createdAt')->since(),
                ])),

            // many-to-many: Post belongsToMany Tag through post_tag
            Relation::make('tags')
                ->manyToMany(TagResource::class)
                ->pivotTable('post_tag')
                ->pivotKeys(parent: 'post_id', related: 'tag_id')
                ->pivotColumns(['sort'])     // pivot attrs: table columns + attach-form fields
                ->recordTitle('name'),
        ];
    }
}
```

> **Resource cross-references are app-level, not framework-internal.**
> `CommentResource::class` / `TagResource::class` are the integrator's own resources;
> `::class` stores a string at compile time, and the framework instantiates the target
> only at runtime **through `ResourceRegistry`** (by class-string → registered
> instance). No framework package depends sideways on another (rule 2); the descriptor
> holds a string, not a live dependency.

`Relation` (fluent builder; resolution surface `@internal`):

```php
namespace Atrium\Relation;

final class Relation
{
    public static function make(string $name): self;   // the relation's URL/identity key

    // -- Kind (sets the action taxonomy) -----------------------------------
    /** @param class-string<AdminResource> $target */
    public function oneToMany(string $target): self;
    /** @param class-string<AdminResource> $target */
    public function manyToMany(string $target): self;

    // -- Keys (executed by the adapter; never introspected) ----------------
    public function foreignKey(string $column): self;                  // 1:M: child FK → parent
    public function pivotTable(string $table): self;                   // M:N
    public function pivotKeys(string $parent, string $related): self;  // M:N pivot FK columns
    /** @param list<string> $columns */
    public function pivotColumns(array $columns): self;                // M:N edge attributes

    // -- Presentation ------------------------------------------------------
    public function label(string|\Closure $label): self;               // tab/section title (default: humanised name)
    public function icon(?string $icon): self;                         // tab icon
    public function recordTitle(string|\Closure $attribute): self;     // titles a record in the picker
    public function table(\Closure $configure): self;                  // fn(TableConfiguration): TableConfiguration
    public function form(\Closure $configure): self;                   // fn(Schema): Schema (create/edit; default: target's form())
    public function emptyState(string $heading, ?string $description = null, ?string $icon = null): self;

    // -- Visibility / read-only -------------------------------------------
    public function visible(bool|\Closure $condition = true): self;    // fn(object $parent): bool
    public function readOnlyOnView(bool $readOnly = true): self;       // default true

    // -- Extraction (rule 6) ----------------------------------------------
    /** @param class-string<RelationManager> $class */
    public function using(string $class): self;                       // delegate to a dedicated config class
}
```

Defaults that keep config small: no `->table()` reuses the **target resource's
`table()`**; no `->form()` reuses its `form()`; `recordTitle` defaults to the target's
`getIdentifierField()` if unset; `label` defaults to the humanised relation name;
`emptyState` defaults to a per-kind message ("No {related} yet").

**The extracted config class** (`->using(...)`) is a plain PHP class — *not* a Live
Component — mirroring how a `Page` is a descriptor, not a component:

```php
final class CommentsRelationManager extends RelationManager
{
    public function table(TableConfiguration $t): TableConfiguration { /* … */ }
    public function form(Schema $schema): Schema { /* … */ }
}
```

The framework constructs it with `new` when resolving the descriptor (like `Page`
subclasses) and merges its `table()`/`form()` over the descriptor's defaults.

### 5.2 Rendering: a shared record-table core (`REL-03`)

The list table (`DataTable`) and a relation manager are the same *picture* — columns,
search, sort, pagination, a header/row/bulk action bar — over different *data* and
*actions*. The naïve "reuse `data_table.html.twig`" is a trap: `DataTable` is bound
to one resource+slug (it builds its query from `resource()->scopeQuery()`, derives
header actions from `ListPage`, fetches via `DataProviderInterface::fetch`, and builds
flat-URL `ActionContext`s). So we **extract the shared core** rather than overload
`DataTable`:

- **`Atrium\Twig\Components\AbstractRecordTable`** — an abstract Live Component owning
  the presentation contract the Twig partial calls: `getColumns()`, `getRows()`,
  `getHeaderActions()`, `getRowActions()`, `getBulkActions()`, search/sort/pagination
  LiveProps + getters, empty-state. It defines the data path as two abstract seams:
  `fetchPage(DataQuery): iterable` and `total(DataQuery): int`, and an
  `actionContext(object $record): ActionContext` factory.
- **`templates/components/_record_table.html.twig`** — the shared toolbar + `<thead>`/
  `<tbody>` + action-bar markup, rendered against that contract. `data_table.html.twig`
  becomes a thin wrapper that `{% include %}`s it.
- **`DataTable extends AbstractRecordTable`** — `fetchPage`/`total` delegate to
  `DataProviderInterface::fetch`/`count` with `resource()->scopeQuery()`; header
  actions come from `ListPage`; `actionContext` is the flat one. **Behaviour
  unchanged** (covered by the existing table test-suite as a regression guard).
- **`RelationManager extends AbstractRecordTable`** — `fetchPage`/`total` delegate to
  `RelationDataProvider::listRelated`/`countRelated` (parent + descriptor + the
  target-scoped `DataQuery`); header/row/bulk actions are the relation set (§5.3);
  `actionContext` is flat for an inline relation, or a `NestedActionContext` when the
  target is a nested resource (§5.4).

This is an honest shared core — one contract, one HTML, two data sources — not a fork.
The `DataTable` refactor is a scoped, test-guarded improvement of code this feature
must build on, and is the first milestone step (REL-M1).

`RelationManager` is mounted by the host (§5.4):

```twig
{{ component('Atrium:RelationManager', {
    resource: parentResourceSlug,
    parentId: entityId,
    relation: 'comments',
    page: 'edit',          {# 'edit' | 'view' — drives read-only #}
}) }}
```

Its component **key** is `{parentSlug}:{parentId}:{relation}` so each manager keeps its
own page/search/sort state independently and stably across tab switches and parent
re-renders. It resolves the parent resource (registry, by slug) and the target resource
(registry, by the descriptor's class-string).

### 5.3 The action family — `Atrium\Table\Action`

New actions, all reusing the `Action` subsystem (label/icon/color/confirmation/
authorize/visible), bound to the relation context (parent record + descriptor).

| Action | Kind | Role | Semantics |
| --- | --- | --- | --- |
| `CreateAction` | both | header | Create an owned record, **pre-linked** to the parent (FK set / pivot row written) — see the create-flow note below. |
| `EditAction` / `ViewAction` | both | row | Edit/View the owned record (its own form/view), parent-scoped. |
| `DeleteAction` / `BulkDeleteAction` | both | row/bulk | Delete the owned record. |
| `AssociateAction` | 1:M | header | Pick an **existing** record; **set** its FK to the parent. |
| `DissociateAction` | 1:M | row | **Clear** the record's FK (record persists; integrator's responsibility to enable only for nullable FKs — §3). |
| `AttachAction` | M:N | header | Pick an existing record; **insert a pivot row** (+ pivot fields). |
| `DetachAction` / `DetachBulkAction` | M:N | row/bulk | **Remove the pivot row** (record untouched). |

- **Create/Edit flow.** For an **inline** relation manager, create/edit open a
  **modal server-action on the `RelationManager`** (the descriptor's/target's `form()`
  rendered in a dialog); create writes the owned record via `DataWriterInterface` and
  sets the link (FK or pivot) inside one transaction. For a relation pointing at a
  **nested** resource (§5.4), "New" and row Edit are **links to the child's nested
  pages** instead. So the header "create" affordance is a `CreateAction` variant whose
  URL/behaviour is chosen by whether the target is nested — not the flat
  `CreateAction::getStandaloneUrl()`.
- **Associate/Attach pickers.** Render a `Select` over the target resource titled by
  `recordTitle`, its options drawn from **`RelationDataProvider::listLinkable`**
  (server-side exclusion of already-linked + pagination — §5.5), **not** a client-side
  filter of a full fetch. `AttachAction` appends the pivot fields (`pivotColumns`) to
  the same form; the related table shows those pivot columns.
- Link/unlink call the new `RelationDataProvider` methods inside the existing
  transactional boundary; each action carries its own `authorize()` mapping to the
  abilities in §5.5/§7.

### 5.4 Placement & nesting

**Placement host — `Atrium\Twig\Components\RelationManagers`.** The schema-level
`Tabs` component holds active-tab state inside a *host schema component* (`Form`) and
renders through the layout renderer — it is **not** a page-level widget and cannot be
dropped above sibling Live Components. So multi-relation placement uses a dedicated
lightweight host Live Component:

- It reads the resource's `relations()` (filtered by `->visible($parent)` and, on the
  view page, all of them with read-only managers).
- **One relation** → renders the single `RelationManager` in a titled section.
- **Several** → renders a server-driven **tab strip**; the host holds
  `#[LiveProp] public string $activeRelation` and mounts **only the active**
  `RelationManager` (others are tab buttons until selected), so server load is one
  manager at a time and each keeps its own state via its stable key (§5.2).
- The Edit page template renders `{{ component('Atrium:RelationManagers', {resource,
  parentId, page: 'edit'}) }}` after the form; the View template renders it (page:
  'view') after the entries. Both pages already host Live Components, so this is
  additive.

**Nested resources — `Atrium\Relation\ParentRelation`.**

```php
final class LessonResource extends AdminResource
{
    public function parent(): ?ParentRelation
    {
        return ParentRelation::make(CourseResource::class)
            ->relationship('lessons')   // the parent's Relation name (§5.1) holding these children
            ->foreignKey('course_id');  // child FK back to the parent (must equal that relation's foreignKey)
    }
}

final class ParentRelation
{
    /** @param class-string<AdminResource> $parent */
    public static function make(string $parent): self;
    public function relationship(string $name): self;
    public function foreignKey(string $column): self;   // child → parent FK column
    // resolution helpers @internal
}
```

The base `AdminResource::parent()` returns `null` (not nested). Boot validation
(REL-20) asserts the named parent relation exists and its `foreignKey` matches.

**Routing.** `config/routes.php` gains an explicit nested family. Current routes:

```
atrium_dashboard         /{prefix}
atrium_resource_create   /{prefix}/{resource}/new
atrium_resource_edit     /{prefix}/{resource}/{id}/edit
atrium_resource_view     /{prefix}/{resource}/{id}
atrium_page              /{prefix}/{slug}
```

The nested block is inserted **before `atrium_page`** and after the flat resource
routes:

```php
// nested: parent segment + child resource; literals (/new, /edit) before the
// bare {id} so they win, mirroring the flat-route ordering rule.
$routes->add('atrium_nested_create', $prefix.'/{parentResource}/{parentId}/{resource}/new')
    ->controller([AdminController::class, 'nestedCreate'])
    ->requirements(['parentResource' => '[a-z0-9-]+', 'resource' => '[a-z0-9-]+', 'parentId' => '[^/]+']);
$routes->add('atrium_nested_edit', $prefix.'/{parentResource}/{parentId}/{resource}/{id}/edit')
    ->controller([AdminController::class, 'nestedEdit'])
    ->requirements(['parentResource' => '[a-z0-9-]+', 'resource' => '[a-z0-9-]+', 'parentId' => '[^/]+', 'id' => '[^/]+']);
$routes->add('atrium_nested_view', $prefix.'/{parentResource}/{parentId}/{resource}/{id}')
    ->controller([AdminController::class, 'nestedView'])
    ->requirements(['parentResource' => '[a-z0-9-]+', 'resource' => '[a-z0-9-]+', 'parentId' => '[^/]+', 'id' => '[^/]+']);
$routes->add('atrium_nested_index', $prefix.'/{parentResource}/{parentId}/{resource}')
    ->controller([AdminController::class, 'nestedIndex'])
    ->requirements(['parentResource' => '[a-z0-9-]+', 'resource' => '[a-z0-9-]+', 'parentId' => '[^/]+']);
```

**Non-collision argument (Symfony matches by declaration order, not depth):**

- All flat resource routes are 1–4 segments after the prefix with these shapes:
  `/{resource}/new` (2), `/{resource}/{id}/edit` (3), `/{resource}/{id}` (2),
  `/{slug}` (1). The nested routes are **3–5 segments** (`/{p}/{pid}/{r}` …
  `/{p}/{pid}/{r}/{id}/edit`). A path with **3+ segments cannot match** any flat route
  (their patterns have at most 3 placeholders and fixed literal positions; `{id}` is
  `[^/]+` so it never spans a `/`). Thus no nested path is shadowed by, and none
  shadows, a flat route — independent of ordering. Within the nested block, the `/new`
  and `/{id}/edit` literal variants are declared **before** the bare `/{id}` and
  `/{parentResource}/{parentId}/{resource}` index so the literals win (the existing
  ordering rule, applied one level deeper). Resource slugs are constrained to
  `[a-z0-9-]+`, so a literal segment like `new`/`edit` is disambiguated by position,
  not value.

**Controller (house-style pseudo-code, mirrors `view()`):**

```php
public function nestedView(string $parentResource, string $parentId, string $resource, string $id): Response
{
    $parent = $this->requireResource($parentResource);
    $child  = $this->requireResource($resource);
    $this->denyUnless($parent->canAccess() && $child->canAccess());

    // Scoped parent resolution (reuses getIdentifierField + scopeFilters).
    $parentRecord = $this->dataProvider?->find(
        $parent->getEntityClass(), $parentId, $parent->scopeFilters(), $parent->getIdentifierField(),
    );
    if (null === $parentRecord) { throw new NotFoundHttpException(/* … */); }

    // Child scoped to BOTH its own scopeQuery and the parent FK; cross-parent ⇒ 404.
    $childFilters = $child->scopeFilters() + [$child->parent()->foreignKeyColumn() => $parentId];
    $record = $this->dataProvider?->find(
        $child->getEntityClass(), $id, $childFilters, $child->getIdentifierField(),
    );
    if (null === $record) { throw new NotFoundHttpException(/* … */); }
    $this->denyUnless($child->canView($record));

    // … render the child's view page with a NestedActionContext + breadcrumb …
}
```

**URLs & breadcrumbs.** A `NestedActionContext extends ActionContext` adds the
`{parentResource}/{parentId}` prefix so `EditAction`/`ViewAction`/`DeleteAction` used
in a nested table emit 5-segment URLs unchanged. `PageContext` gains
`parentRecords: list<object>` (resolved ancestry, nearest last) and
`nestedUrl(action, id?)`. The breadcrumb chrome renders the ancestry recursively (each
ancestor titled via its `recordTitle`/`getIdentifierField`). A parent that is itself
nested contributes its own ancestors first.

### 5.5 The data layer — `Atrium\DataProvider\RelationDataProvider`

```php
namespace Atrium\DataProvider;

use Atrium\Relation\RelationDescriptor;   // @internal resolved view of a Relation

interface RelationDataProvider
{
    /** @return iterable<object> */
    public function listRelated(RelationDescriptor $r, object $parent, DataQuery $query): iterable;
    public function countRelated(RelationDescriptor $r, object $parent, DataQuery $query): int;

    /** Candidate records for an Associate/Attach picker: NOT already linked. @return iterable<object> */
    public function listLinkable(RelationDescriptor $r, object $parent, DataQuery $query): iterable;
    public function countLinkable(RelationDescriptor $r, object $parent, DataQuery $query): int;

    public function associate(RelationDescriptor $r, object $parent, object $child): void;   // 1:M set FK
    public function dissociate(RelationDescriptor $r, object $parent, object $child): void;   // 1:M clear FK

    /** @param array<string, scalar|null> $pivot */
    public function attach(RelationDescriptor $r, object $parent, object $child, array $pivot = []): void;  // M:N
    public function detach(RelationDescriptor $r, object $parent, object $child): void;                      // M:N
}
```

- **Query scoping (who applies what).** The `RelationManager` applies the **target
  resource's `scopeQuery()`** to the `DataQuery` *before* calling `listRelated`/
  `listLinkable` (mirroring how `DataTable::query()` applies `scopeQuery` inline) — so
  the adapter never needs the resource. `listRelated` then composes the **parent
  scope** on top: 1:M adds `child.<foreignKey> = :parentId`; M:N joins the pivot on the
  two pivot keys. `listLinkable` instead **excludes** already-linked: 1:M
  `WHERE <foreignKey> IS NULL` (a child has at most one parent), M:N `WHERE id NOT IN
  (SELECT related_key FROM pivot WHERE parent_key = :parentId)` — both paginated via the
  same `DataQuery`.
- **Writes & transactions.** `associate`/`dissociate` set/clear the FK; `attach`/
  `detach` insert/delete the pivot row (with pivot columns) via **DBAL, without mapping
  the pivot as an entity**. When an action both writes an owned record *and* links it
  (e.g. `CreateAction`: `DataWriterInterface::create` + `attach`), the manager wraps
  **both in one `DataWriterInterface::transactional()`** call. For the Doctrine
  adapter, `DoctrineRelationProvider` and `DoctrineDataWriter` share the same
  `EntityManager` **connection**, so the DBAL pivot write enlists in that transaction.
  For the **array adapter**, the pivot is an in-memory list of
  `[parentId, relatedId, pivotAttrs]` tuples and writes are ordered, not transactional
  (consistent with the array writer today).
- **Adapter placement.** A sibling `DoctrineRelationProvider` keeps `DoctrineDataProvider`
  focused; an `ArrayRelationProvider` backs the array adapter. Both bind values as
  parameters; column/table names come from the **trusted descriptor**, never user input.
- Core passes the **descriptor + parent + child**; the adapter owns all backend
  specifics. No Doctrine type crosses into core.

### 5.6 Configuration surface (summary)

| Layer | Knobs |
| --- | --- |
| **Existence** | `relations()` per resource; `->visible(fn($parent))`; owned actions opt out via the target's `can*()`. |
| **Kind & keys** | `->oneToMany()/->manyToMany()`, `->foreignKey()`, `->pivotTable()/->pivotKeys()/->pivotColumns()`. |
| **Manager content** | `->table()` (default: target's `table()`), `->form()` (default: target's `form()`), `->recordTitle()`, `->label()/->icon()`, `->emptyState()`. |
| **Placement** | Tabs vs section (auto by count, host-driven); `->readOnlyOnView()`. |
| **Actions** | Per-kind link/unlink + owned create/edit/view/delete + bulk; each action's own `authorize()/visible()/confirmation`. |
| **Nesting** | `parent()` → `ParentRelation::make()->relationship()->foreignKey()`; nested routes + `NestedActionContext` + recursive breadcrumb; boot-validated. |
| **Authorization** | Owned → target abilities; link/unlink → `canAssociate`/`canDissociate`/`canAttach`/`canDetach`; `scopeQuery()` on both sides. |
| **Extraction** | `->using(RelationManager::class)` (a plain config class). |

## 6. Rendering

- **Shared core (§5.2):** `_record_table.html.twig` drives both `DataTable` and
  `RelationManager` from one contract; the relation manager adds the kind-specific
  action bar and (M:N) pivot columns. Its confirmation modal + pickers reuse the shared
  `confirm_modal` and `Select` partials.
- **Host band:** `RelationManagers` renders a single titled section or a server-driven
  tab strip (active manager only), placed after the Edit form and after the View
  entries. Empty-state per relation from `->emptyState()`.
- **Nested chrome:** a breadcrumb partial renders `PageContext.parentRecords`; nested
  links carry the parent segment via `NestedActionContext`.

## 7. Authorization

Owned actions reuse the **target resource's** `create`/`edit`/`view`/`delete`
(`can(...)`). Link/unlink add **first-class hooks on `AdminResource`** —
`canAssociate($parent, $child)`, `canDissociate(...)`, `canAttach(...)`,
`canDetach(...)` — default-allow, dispatched through `can('associate'|…, …)` so actions
gate uniformly. These are **public API additions** (BC-relevant per rule 4; flagged in
CHANGELOG). Every action is **hidden when denied and re-checks at execution** (Live
endpoints are POST-able directly). The related list always also passes through the
**target resource's `scopeQuery()`** (a relation never surfaces a hidden row); nested
child operations add the **parent-scope** filter so a child of another parent 404s.

## 8. Milestones

### REL-M1 — Shared table core + descriptor + read-only 1:M manager (`REL-01..04`, `REL-10`, `REL-11` read side)
- **Extract `AbstractRecordTable`** + `_record_table.html.twig` from `DataTable`;
  `DataTable` re-expressed on it with **no behaviour change** (existing table tests are
  the regression guard).
- `Atrium\Relation\Relation` (kind/keys/presentation) + `@internal RelationDescriptor`;
  target/registry resolution; boot validation for unregistered targets (`REL-20` part).
- `RelationDataProvider` interface + **Doctrine** and **array** `listRelated`/
  `countRelated` for **one-to-many** (FK scope composed with the target's `scopeQuery()`
  + `DataQuery`). Link/unlink/linkable methods contracted (stubs).
- `RelationManager extends AbstractRecordTable` rendering a parent-scoped **read-only**
  table; mounted on the **Edit** page via the `RelationManagers` host (single-section
  path).
- Unit: descriptor resolution + boot validation; `listRelated`/`countRelated` FK scope
  + `scopeQuery` composition + pagination (both adapters); `AbstractRecordTable`
  contract. Functional: a parent's Edit page shows the scoped related table; `DataTable`
  regression suite green.

### REL-M2 — One-to-many actions (`REL-05`, `REL-06` 1:M, `REL-07` associate, `REL-09`, `REL-12`, `REL-13`)
- Owned `Create`/`Edit`/`View`/`Delete` (+ bulk) scoped to the parent (create sets the
  FK, in one transaction); **`AssociateAction`/`DissociateAction`** with a
  `listLinkable`-backed picker (FK IS NULL exclusion, paginated).
- `readOnlyOnView` (default true) on the View page; `visible(fn($parent))`;
  per-relation `emptyState`.
- **`canAssociate`/`canDissociate`** hooks + the owned-action target-ability gating,
  re-checked at execution.
- Unit: associate sets FK / dissociate clears it (both adapters); `listLinkable`
  exclusion; read-only-on-view hides mutators; auth denial hides + refuses. Functional:
  full 1:M lifecycle on Edit.

### REL-M3 — Many-to-many + tabs (`REL-02` M:N, `REL-06` M:N, `REL-07` pivot, `REL-08`)
- `manyToMany`/`pivot*`; `RelationDataProvider` pivot `listRelated`/`listLinkable`
  (join + NOT IN) + `attach`/`detach` (+ `DetachBulkAction`) with shared-transaction
  semantics; **pivot columns** in the table, **pivot fields** in the attach form.
- `RelationManagers` host **tab strip** (active manager only) for multiple relations;
  `canAttach`/`canDetach`.
- Unit: pivot list/linkable joins, attach with pivot attrs (+ transaction), detach
  leaves the record, exclude-linked; tab host mounts only the active manager. Functional:
  Post ↔ Tags attach/detach with a pivot `sort`.

### REL-M4 — Nested resources (`REL-14..18`, `REL-20` nesting validation)
- `ParentRelation` + `AdminResource::parent()` + boot validation (FK match); nested
  route family + the non-collision ordering; scoped parent resolution; child
  parent-scope filter + create sets the FK; cross-parent 404.
- `NestedActionContext`; `PageContext.parentRecords`/`nestedUrl()`; recursive
  **breadcrumb**. Relation-manager row links → child **nested pages**; "New" → nested
  create.
- Unit: nested-URL generation, parent-scope filter, cross-parent 404, recursive
  ancestry, route ordering (literals win). Functional: `/courses/{id}/lessons/...` full
  CRUD scoped to the course; breadcrumb renders; a lesson of another course 404s.

### REL-M5 — Playground, docs, verify, review (`REL-19`, `REL-21`, `REL-22`)
- `->using()` extraction example; playground: Post → Comments (1:M) + Post ↔ Tags
  (M:N) on the post pages, Course → Lessons **nested**; browser-verify (real Doctrine
  data, dark mode, no console errors).
- Docs: `docs/integration-guide/relations/{overview,nesting}.md`; cross-links;
  `CHANGELOG` `REL-01..22`; PRD pointer.
- **Separate code-review agent** (`REL-22`) reviews the feature; address findings.

## 9. Testing

- **Shared core:** `DataTable` behaviour unchanged after the `AbstractRecordTable`
  extraction (regression); the contract is satisfied by both components.
- **Descriptor & boot:** kind/keys/defaults resolve; `recordTitle`/`label`/`emptyState`
  fallbacks; target `table()`/`form()` reuse; **boot errors** for an unregistered target
  and a mismatched nested `foreignKey`.
- **Data seam (both adapters):** 1:M `listRelated` FK scope + `scopeQuery` composition +
  pagination/search/sort; `listLinkable` exclusion (FK IS NULL / NOT IN pivot) +
  pagination; `associate`/`dissociate` FK set/clear; M:N pivot-join list, `attach` (with
  pivot attrs) / `detach` leaving the record; create+link in **one transaction** (rolls
  back together).
- **Manager & host:** parent-scoped list renders; read-only-on-view hides mutators;
  `visible(fn($parent))`; single section vs tab strip (active-only mount); stable
  component key preserves per-manager state.
- **Authorization:** owned actions follow target abilities; link/unlink follow
  `canAssociate`/`canDissociate`/`canAttach`/`canDetach`; denied actions hidden **and**
  refuse at execution; related list never exceeds the target's `scopeQuery()`.
- **Nesting:** nested-route resolution + ordering (literals win); scoped parent
  resolution (out-of-scope parent → 404); child cross-parent access → 404; nested URL
  generation via `NestedActionContext`; recursive breadcrumb; create sets the FK.
- **Non-Doctrine proof:** the array adapter passes the same relation behaviours.
- Gates: `composer test && composer phpstan && composer cs` green; **`KernelBootTest`
  resource count updated** for new fixtures.

## 10. Resolved decisions

1. **Explicit PHP descriptor** drives everything (UI, actions, data calls, nesting);
   no Doctrine in core, no introspection in v1.
2. **one-to-many + many-to-many** in v1 (associate/dissociate vs attach/detach + pivot
   attrs); `HasManyThrough`/polymorphic deferred.
3. **Shared `AbstractRecordTable` core** extracted from `DataTable`; `RelationManager`
   extends it (one contract + HTML, two data sources) — not a template fork, not a
   `DataTable` parent-scope mode.
4. **Target is a resource**, held as a class-string and resolved via `ResourceRegistry`
   (never `new`'d), so the related table/form/auth/identifier are reused and nesting can
   route to its pages.
5. **`RelationDataProvider`** is the storage seam (Doctrine + array), incl. `listLinkable`
   for the picker; relation writes share the `DataWriterInterface` transaction.
6. **Placement host** (`RelationManagers`) owns single-section vs server-driven tabs
   (active-only mount); the schema-level `Tabs` is not reused at page level.
7. **Nested resources** via `parent()`/`ParentRelation` (FK, boot-validated), an
   explicit scoped nested route family proven non-colliding, a `NestedActionContext` for
   URLs, and recursive breadcrumbs; a nested resource still uses a relation manager on
   the parent as its entry point, row links upgraded to full pages.
8. **Authorization** reuses target-resource abilities for owned actions and adds
   first-class `canAssociate`/`canDissociate`/`canAttach`/`canDetach` hooks (BC-relevant),
   hidden-and-enforced.
9. **Inline-first, extractable** to a dedicated `RelationManager` config class (rule 6).
