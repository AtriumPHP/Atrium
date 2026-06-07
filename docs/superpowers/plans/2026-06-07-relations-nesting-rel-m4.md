# REL-M4 — Nested Resources Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a resource become a *nested resource* scoped under a parent record — `/admin/{parentResource}/{parentId}/{resource}/...` — with full CRUD scoped to the parent, cross-parent 404s, a breadcrumb trail, and parent-page relation managers that link into the child's nested pages instead of inline modals.

**Architecture:** A child resource declares `parent(): ?ParentRelation` (parent class + the parent relation name + the child→parent FK). A lazy `ParentRelationResolver` validates the declaration against the registry on first use (matching today's `assertNoSlugCollisions`/`RelationResolver` lazy pattern — no compiler pass). A new explicit nested route family (`atrium_nested_{create,edit,view,index}`) dispatches to four new `AdminController` actions that resolve the parent record (scoped), resolve the child record (scoped to its own `scopeQuery` **and** the parent FK — cross-parent ⇒ 404), and render the existing form/view/list templates parameterised with parent context. URL generation reuses the existing `ActionContext`/`PageContext` by adding a `NestedActionContext` subclass that overrides `resourceUrl()` to prepend the `{parentResource}/{parentId}` segment — so every record action (`Edit`/`View`/`Delete`) and `rowUrl` it already builds becomes 5-segment with no per-action change. `DataTable` and `RelationManager` get a `parentId` mount prop that flips them into nested mode (parent-FK list scope + `NestedActionContext`); `RelationManager` additionally swaps its inline create/edit modals for links to the child's nested pages (REL-18). A recursive-ready breadcrumb partial renders `PageContext.parentRecords`.

**Tech Stack:** PHP 8.4, Symfony 7 (Routing, HttpKernel, PropertyAccess), Symfony UX Live Components, Twig, Tailwind. Tests: PHPUnit (`composer test`), PHPStan max (`composer phpstan`), PHP-CS-Fixer Symfony ruleset (`composer cs`).

**Gate after every task:** `composer test && composer phpstan && composer cs` (all green before commit).

---

## Review corrections (folded after plan-review against the real codebase)

A plan-review agent verified every assumption against the actual code. Corrections, applied inline in the tasks below and summarised here:

- **[B1] `AdminController` DI is explicit/positional, not autowired.** `config/services.php:41-50` lists `AdminController` args by hand, ending with `service(DataProviderInterface::class)->ignoreOnInvalid()`. The two new constructor params (`ParentRelationResolver`, `property_accessor`) must be inserted **before** that nullable last arg, and `ParentRelationResolver` registered as a service (like `RelationResolver` at `:52`). Task 5 Step 3 now shows the exact corrected `->args([...])` block.
- **[B2] The form field class is `Atrium\Form\Field\TextField`, not `TextInput`.** Fixtures corrected (Task 2).
- **[B3] The view entry class is `Atrium\View\TextEntry`, not `Atrium\View\Entry\TextEntry`.** Fixtures corrected (Task 2).
- **[S1] `recordTitle` titles the *parent* record**, so `TaskResource` uses `->recordTitle('name')` (Project's `name`), and the resolver test expects `'name'`. **This is now the value from Task 2 onward** (the earlier draft's `'title'` is gone — no mid-plan flip), so Task 8's correction step is removed.
- **[S2] `view_page.html.twig` Back link is hard-coded** to the flat URL — Task 8 now makes it `backUrl`-driven too (a nested view's Back must go to `/admin/project/1/task`, not `/admin/task` which 404s).
- **[S3] `DataTable` constructor:** the new `ParentRelationResolver` must go **before** `DataWriterInterface $writer`/`$accessor` (those are forwarded to `parent::__construct`). Task 7 shows the full constructor.
- **[S5] Preset FK type:** `Task.projectId` is `?int`; passing the raw URL string would trip PropertyAccess. `nestedCreate` now reads the typed id from the already-resolved parent record: `$this->accessor->getValue($ctx['parentRecord'], $ctx['parent']->getIdentifierField())`.

Verified sound and unchanged: slugs (`Project`→`project`, `Task`→`task`), route non-collision, `ArrayDataProvider::fetch()` applies `DataQuery.filters` on the list path (so the FK scope works), `Form::save()` applies `presetValues` in non-embedded mode, `DataTable::mount(... ?string $parentId = null)` is additive, `ActionContext` lift-`final` + override compiles at PHPStan max, `RelationManager` ctor addition is autowired, `WebTestCase`/`createClient()` is the HTTP-test idiom, boot count 28→30.

---

## Background the engineer must know

Read these before starting; the tasks reference them.

- **`src/Action/ActionContext.php`** — `final readonly` DTO `(pathPrefix, slug, recordId)` with `resourceUrl()` → `/admin/{slug}`, `recordRootUrl()` → `/admin/{slug}/{id}` (the View URL), `recordUrl($action)` → `/admin/{slug}/{id}/{action}`. `recordUrl`/`recordRootUrl` both build on `resourceUrl()`. **Task 3 lifts `final` and overrides `resourceUrl()` in a subclass — every other method then produces 5-segment URLs for free.**
- **`src/Page/PageContext.php`** — `final readonly` DTO `(resourceSlug, pathPrefix, ?entityId, singularLabel, pluralLabel)` with `indexUrl()`/`createUrl()`/`editUrl($id)`/`viewUrl($id)`. Task 8 extends it.
- **`src/Controller/AdminController.php`** — `final readonly`. Has `create(slug)`, `edit(slug,id)`, `view(slug,id)`, private `resourceList(slug)`, helpers `requireResource(slug): AdminResource` (404 if not registered), `denyUnless(bool)` (403), `pageContext(resource, slug, ?id): PageContext`, `panel(?active): array`, `render(template, ctx): Response`. The record-resolution idiom is `$this->dataProvider?->find($entityClass, $id, $resource->scopeFilters(), $resource->getIdentifierField())`; `null` ⇒ `NotFoundHttpException`. The constructor's `$dataProvider` is **nullable** (a no-DB install renders forms without resolving). Tasks 5–7 add four nested actions + a shared resolver helper; Task 8 injects `PropertyAccessorInterface` for breadcrumb titles.
- **`config/routes.php`** — five routes today: `atrium_dashboard` `/{prefix}`, `atrium_resource_create` `/{prefix}/{resource}/new`, `atrium_resource_edit` `/{prefix}/{resource}/{id}/edit`, `atrium_resource_view` `/{prefix}/{resource}/{id}`, `atrium_page` `/{prefix}/{slug}`. `resource`/`slug` requirement `[a-z0-9-]+`, `id` requirement `[^/]+`. Task 4 inserts the nested family **after `atrium_resource_view` and before `atrium_page`**.
- **`src/DataProvider/DataProviderInterface.php`** — `find(string $entityClass, int|string $id, array $filters = [], string $idField = 'id'): ?object`. `$filters` is a trusted `field => scalar|bool|null` equality map; values bound as parameters. `AdminResource::scopeFilters(): array` extracts the resource's `scopeQuery()` equalities. PHP array union (`+`) merges `scopeFilters()` with the extra FK condition (left wins on key clash — fine, they're disjoint).
- **`src/Resource/AdminResource.php`** — abstract base; `getEntityClass()`, `getIdentifierField(): string = 'id'`, `getSlug()`, `getSingularLabel()`, `getLabel()`, `canAccess()`, `canCreate()`, `canEdit($r)`, `canView($r)`, `scopeQuery()`, `scopeFilters()`, `relations(): list<Relation>`, `resolvePage($action): ?Page`. **No `parent()` yet** — Task 1 adds it.
- **`src/Relation/Relation.php`** — builder; `getName()`, `getKind(): RelationKind`, `getForeignKey(): ?string`, `getTargetClass()`. A 1:M relation has a non-null `foreignKey`.
- **`src/Relation/RelationResolver.php`** — `final readonly`, ctor `(ResourceRegistry $registry)`; `targetResource(Relation): AdminResource` throws if the target isn't registered (lazy REL-20 pattern to mirror).
- **`src/Resource/ResourceRegistry.php`** — ctor `__construct(iterable $resources = [])`; `add()`, `getBySlug($slug)`, `getByClass($class)` (throws if absent), `hasSlug($slug)`. Unit tests build one directly: `new ResourceRegistry([$parentResource, $childResource])`.
- **`src/Twig/Components/AbstractRecordTable.php`** — shared table core. `query()` and `allMatchingQuery()` build a `DataQuery` whose `filters:` come from the private `resolvedFilters()` (configured `Filter`s). `getRows()` emits each row's `url` from `rowUrl($record, $context)`; `getRowAction(): ?string` (null ⇒ navigate via `url`; a string ⇒ row click triggers that Live action). **Task 7 adds a `protected function extraFilters(): array { return []; }` seam merged into both queries**; DataTable overrides it for the parent FK scope.
- **`src/Twig/Components/DataTable.php`** — `mount(string $resource, string $pathPrefix = '', ?int $perPage = null)`; `actionContext(?id)` returns `new ActionContext($this->pathPrefix, $this->resource, $id ?? '')`; `rowUrl()` switches on `tableConfig()->getRecordUrl()` (`'view'`→`recordRootUrl()` when `canReachPage('view')`, `'edit'`→`recordUrl('edit')` when `canReachPage('edit')`); `canReachPage($action,$record)` = `resolvePage($action) !== null` (and the relevant `can*`). Mounted in `templates/admin/resource.html.twig` via `<twig:Atrium:DataTable :resource="resource.slug" :pathPrefix="panel.pathPrefix" />`.
- **`src/Twig/Components/RelationManager.php`** — `mount(resource, parentId, relation, pathPrefix, screen)`; `target(): AdminResource` (the child resource), `parentResource(): AdminResource` (`registry->getBySlug($this->resource)`), `descriptor()`, `actionContext(?id)` returns a flat `ActionContext` over the **target** slug, `rowUrl()` returns `null` (the explicit M4 seam), `getRowAction()` returns `'openEdit'` for 1:M / `null` for M:N, `canCreateRelated()` gates the New button, `openCreate()` opens the inline modal. Template `templates/components/relation_manager.html.twig` renders the New button as `data-live-action-param="openCreate"`.
- **`src/Twig/Components/RelationManagers.php`** + `templates/components/relation_managers.html.twig` — host band; mounts `Atrium:RelationManager` per visible relation. No change needed for M4 (the manager self-detects nested mode).
- **`templates/admin/{form_page,view_page,resource}.html.twig`** + **`templates/admin/layout.html.twig`** — pages extend `layout`; the header `<h1>` is `{% block heading %}`, actions in `{% block header_actions %}`, body in `{% block body %}`. The "Back to {resource}" link is hard-coded to `{{ panel.pathPrefix }}/{{ resource.slug }}`. Task 8 adds a breadcrumb above the body and makes Back nested-aware.
- **Test harness** — `tests/Functional/AtriumTestKernel.php` registers fixture resources as services (≈ line 125–237) and aliases `DataProviderInterface`→`ArrayDataProvider`, `RelationDataProvider`→`ArrayRelationProvider`, `DataWriterInterface`→`ArrayDataWriter`; `SampleData` (DI service) seeds shared in-memory records. `tests/Functional/KernelBootTest.php` asserts the registered-resource **count = 28** (bump it when adding fixtures). Functional tests boot the kernel; Live-action tests add `protected function tearDown(): void { parent::tearDown(); restore_exception_handler(); }`. HTTP page tests use the framework test client — see `tests/Functional/PagesTest.php`/`RecordViewTest.php` for the `static::createClient()` + `$client->request('GET', ...)` idiom and asserting status / crawler text.

### Fixtures introduced by this plan (Task 2)

A self-contained nested pair, isolated from the existing Post/Comment/Tag fixtures so no current test changes behaviour:

- Entity `Project` `(int $id, string $name)` — the parent.
- Entity `Task` `(int $id, string $title, ?int $projectId)` — the child, nested under a project.
- `ProjectResource` (slug `project`) — `relations()` declares `Relation::make('tasks')->oneToMany(TaskResource::class)->foreignKey('projectId')`; has a `view()` so the breadcrumb parent link resolves to a View page.
- `TaskResource` (slug `task`) — declares `parent()` → `ParentRelation::make(ProjectResource::class)->relationship('tasks')->foreignKey('projectId')`; has `form()` and `view()` so nested create/edit/view all render.

Seed (`SampleData`): projects `[1 "Alpha", 2 "Beta"]`; tasks `[1 "Design" project 1, 2 "Build" project 1, 3 "Ship" project 2]`. Cross-parent probe: `/admin/project/2/task/1` ⇒ 404 (task 1 is under project 1); `/admin/project/1/task/1` ⇒ 200.

---

## File structure

**New source files**
- `src/Relation/ParentRelation.php` — the developer-facing nesting declaration (value-object builder). Public API.
- `src/Relation/ResolvedParentRelation.php` — `@internal` immutable resolved view (parent resource + parent relation + FK + breadcrumb title attribute).
- `src/Relation/ParentRelationResolver.php` — `@internal` lazy validator/resolver (registry lookup + relation-exists + FK-match + 1:M assertions).
- `src/Action/NestedActionContext.php` — `ActionContext` subclass prepending the `{parentResource}/{parentId}` segment.
- `templates/admin/_breadcrumb.html.twig` — recursive-ready breadcrumb partial.

**Modified source files**
- `src/Action/ActionContext.php` — lift `final` (now `readonly`, intentionally extensible).
- `src/Resource/AdminResource.php` — add `parent(): ?ParentRelation { return null; }`.
- `src/Page/PageContext.php` — add `parentRecords`/`nested context` + `nestedUrl()`.
- `config/routes.php` — nested route family.
- `src/Controller/AdminController.php` — `nestedIndex/nestedCreate/nestedEdit/nestedView` + `resolveNested()` helper + breadcrumb builder; inject `ParentRelationResolver` + `PropertyAccessorInterface`.
- `config/services.php` — register `ParentRelationResolver` (autowired) if not autodiscovered.
- `src/Twig/Components/AbstractRecordTable.php` — `extraFilters()` seam.
- `src/Twig/Components/DataTable.php` — `parentId` mount prop + nested `actionContext()`/`extraFilters()`.
- `src/Twig/Components/RelationManager.php` — nested-mode detection: nested `rowUrl()`/`actionContext()`, `getRowAction()` null when nested, `getCreateUrl()`/`isNested()`, `openCreate()` guarded.
- `templates/admin/{form_page,view_page,resource}.html.twig` — breadcrumb include + nested-aware Back link + pass `parentId`/`parentResourceSlug` into embedded `DataTable`/`Form`/`RelationManagers`.
- `templates/components/relation_manager.html.twig` — New button renders as a nested link when `this.isNested`.

**New test files**
- `tests/Relation/ParentRelationTest.php`
- `tests/Relation/ParentRelationResolverTest.php`
- `tests/Action/NestedActionContextTest.php`
- `tests/Functional/NestedResourceTest.php` (controller CRUD, scoping, 404s)
- `tests/Functional/NestedRoutingTest.php` (route match / non-collision)
- `tests/Functional/NestedBreadcrumbTest.php`
- `tests/Functional/RelationManagerNestedTest.php` (REL-18)

**New fixtures**
- `tests/Fixtures/Entity/Project.php`, `tests/Fixtures/Entity/Task.php`
- `tests/Fixtures/Resource/ProjectResource.php`, `tests/Fixtures/Resource/TaskResource.php`

**Docs**
- `docs/integration-guide/resources/nesting.md` (new), `relations.md` + `overview.md` cross-links, `CHANGELOG.md`, PRD pointer.

---

## Task 1: `ParentRelation` builder + `AdminResource::parent()`

**Files:**
- Create: `src/Relation/ParentRelation.php`
- Modify: `src/Resource/AdminResource.php` (add `parent()` default)
- Test: `tests/Relation/ParentRelationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\ParentRelation;
use Atrium\Tests\Fixtures\Resource\PostRelResource;
use PHPUnit\Framework\TestCase;

final class ParentRelationTest extends TestCase
{
    public function testBuilderExposesParentClassRelationshipAndForeignKey(): void
    {
        $parent = ParentRelation::make(PostRelResource::class)
            ->relationship('comments')
            ->foreignKey('postId');

        self::assertSame(PostRelResource::class, $parent->getParentClass());
        self::assertSame('comments', $parent->getRelationship());
        self::assertSame('postId', $parent->getForeignKey());
        self::assertNull($parent->getRecordTitle());
    }

    public function testRecordTitleIsOptional(): void
    {
        $parent = ParentRelation::make(PostRelResource::class)
            ->relationship('comments')->foreignKey('postId')->recordTitle('title');

        self::assertSame('title', $parent->getRecordTitle());
    }

    public function testRelationshipAccessorThrowsWhenUnset(): void
    {
        $this->expectException(\LogicException::class);
        ParentRelation::make(PostRelResource::class)->getRelationship();
    }

    public function testForeignKeyAccessorThrowsWhenUnset(): void
    {
        $this->expectException(\LogicException::class);
        ParentRelation::make(PostRelResource::class)->getForeignKey();
    }
}
```

(Uses the existing `PostRelResource` fixture purely as a class-string; no nesting behaviour yet.)

- [ ] **Step 2: Run it, expect failure**

Run: `vendor/bin/phpunit tests/Relation/ParentRelationTest.php`
Expected: FAIL — `Class "Atrium\Relation\ParentRelation" not found`.

- [ ] **Step 3: Implement `ParentRelation`**

```php
<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;

/**
 * Declares that a resource is a *nested resource* (REL-14): its records live
 * under a parent record, reached at `/{prefix}/{parentResource}/{parentId}/{resource}/...`.
 * Configured in PHP on a child resource's {@see AdminResource::parent()}. The
 * parent class, the parent relation that holds these children, and the child→parent
 * foreign key are stated explicitly; a {@see ParentRelationResolver} validates them
 * against the registry on first use.
 */
final class ParentRelation
{
    private ?string $relationship = null;
    private ?string $foreignKey = null;
    private ?string $recordTitle = null;

    /** @param class-string<AdminResource> $parent */
    private function __construct(private readonly string $parent)
    {
    }

    /** @param class-string<AdminResource> $parent */
    public static function make(string $parent): self
    {
        return new self($parent);
    }

    /** The name of the parent's {@see Relation} (REL-01) that holds these children. */
    public function relationship(string $name): self
    {
        $this->relationship = $name;

        return $this;
    }

    /** The child column holding the parent's id (must equal that relation's foreignKey). */
    public function foreignKey(string $column): self
    {
        $this->foreignKey = $column;

        return $this;
    }

    /** Attribute used to title the parent record in the breadcrumb (default: the parent's identifier field). */
    public function recordTitle(string $attribute): self
    {
        $this->recordTitle = $attribute;

        return $this;
    }

    /** @return class-string<AdminResource> */
    public function getParentClass(): string
    {
        return $this->parent;
    }

    public function getRelationship(): string
    {
        return $this->relationship ?? throw new \LogicException(\sprintf('Nested resource parent of "%s" has no relationship(); call relationship().', $this->parent));
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey ?? throw new \LogicException(\sprintf('Nested resource parent of "%s" has no foreignKey(); call foreignKey().', $this->parent));
    }

    public function getRecordTitle(): ?string
    {
        return $this->recordTitle;
    }
}
```

- [ ] **Step 4: Add the `parent()` hook to `AdminResource`**

Add near `relations()` (around `src/Resource/AdminResource.php:112`):

```php
    /**
     * Declare this resource is nested under a parent record (REL-14): return a
     * {@see ParentRelation} naming the parent resource, the parent relation that
     * holds these children, and the child→parent foreign key. The default `null`
     * means the resource is top-level (not nested).
     */
    public function parent(): ?ParentRelation
    {
        return null;
    }
```

Add `use Atrium\Relation\ParentRelation;` to the imports.

- [ ] **Step 5: Run the test, expect pass; run gates**

Run: `vendor/bin/phpunit tests/Relation/ParentRelationTest.php` → PASS.
Run: `composer test && composer phpstan && composer cs` → all green.

- [ ] **Step 6: Commit**

```bash
git add src/Relation/ParentRelation.php src/Resource/AdminResource.php tests/Relation/ParentRelationTest.php
git commit -m "Add ParentRelation declaration + AdminResource::parent() hook (REL-14)"
```

---

## Task 2: nesting fixtures + `ParentRelationResolver` (lazy validation)

**Files:**
- Create: `tests/Fixtures/Entity/Project.php`, `tests/Fixtures/Entity/Task.php`
- Create: `tests/Fixtures/Resource/ProjectResource.php`, `tests/Fixtures/Resource/TaskResource.php`
- Create: `src/Relation/ResolvedParentRelation.php`, `src/Relation/ParentRelationResolver.php`
- Modify: `tests/Fixtures/Data/SampleData.php` (seed Project/Task + provider mapping)
- Modify: `tests/Functional/AtriumTestKernel.php` (register the two resources)
- Modify: `tests/Functional/KernelBootTest.php` (count 28 → 30)
- Test: `tests/Relation/ParentRelationResolverTest.php`

- [ ] **Step 1: Create the fixture entities**

`tests/Fixtures/Entity/Project.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

final class Project
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
```

`tests/Fixtures/Entity/Task.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

final class Task
{
    public function __construct(
        public int $id,
        public string $title,
        public ?int $projectId = null,
    ) {
    }
}
```

- [ ] **Step 2: Create the fixture resources**

`tests/Fixtures/Resource/ProjectResource.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Project;
use Atrium\View\TextEntry;

final class ProjectResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Project::class;
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('name')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('name')]);
    }

    public function view(Schema $schema): Schema
    {
        return $schema->fields([TextEntry::make('name')]);
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('tasks')->oneToMany(TaskResource::class)->foreignKey('projectId'),
        ];
    }
}
```

`tests/Fixtures/Resource/TaskResource.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Relation\ParentRelation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Task;
use Atrium\View\TextEntry;

final class TaskResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Task::class;
    }

    public function parent(): ?ParentRelation
    {
        return ParentRelation::make(ProjectResource::class)
            ->relationship('tasks')
            ->foreignKey('projectId')
            // Titles the PARENT (Project) record in the breadcrumb — Project's
            // display attribute is `name`, not `title`.
            ->recordTitle('name');
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('title')])->recordUrl('view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('title')]);
    }

    public function view(Schema $schema): Schema
    {
        return $schema->fields([TextEntry::make('title')]);
    }
}
```

> Imports verified against the codebase: `Atrium\Form\Field\TextField` (there is no `TextInput`), `Atrium\View\TextEntry` (not under `View\Entry\`), and `TableConfiguration::recordUrl('view')` (getter `getRecordUrl()`, consumed by `DataTable::rowUrl()`). Cross-check against `tests/Fixtures/Resource/ViewTagResource.php` + `RowUrlTagResource.php` if anything has drifted.

- [ ] **Step 3: Write the failing resolver test**

`tests/Relation/ParentRelationResolverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\ParentRelation;
use Atrium\Relation\ParentRelationResolver;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Resource\ProjectResource;
use Atrium\Tests\Fixtures\Resource\TaskResource;
use PHPUnit\Framework\TestCase;

final class ParentRelationResolverTest extends TestCase
{
    private function resolver(AdminResource ...$resources): ParentRelationResolver
    {
        return new ParentRelationResolver(new ResourceRegistry($resources));
    }

    public function testResolvesAValidNestedDeclaration(): void
    {
        $resolved = $this->resolver(new ProjectResource(), new TaskResource())
            ->resolve(new TaskResource());

        self::assertInstanceOf(ProjectResource::class, $resolved->parentResource);
        self::assertSame('tasks', $resolved->relation->getName());
        self::assertSame('projectId', $resolved->foreignKey);
        self::assertSame('name', $resolved->recordTitleAttribute); // Project's display attribute
    }

    public function testThrowsWhenResourceIsNotNested(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not nested/');
        $this->resolver(new ProjectResource())->resolve(new ProjectResource());
    }

    public function testThrowsWhenParentResourceIsNotRegistered(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not registered/');
        // TaskResource present, ProjectResource missing from the registry.
        $this->resolver(new TaskResource())->resolve(new TaskResource());
    }

    public function testThrowsWhenNamedRelationshipIsMissing(): void
    {
        $child = new class extends TaskResource {
            public function parent(): ?ParentRelation
            {
                return ParentRelation::make(ProjectResource::class)
                    ->relationship('nope')->foreignKey('projectId');
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/no relation "nope"/');
        $this->resolver(new ProjectResource(), $child)->resolve($child);
    }

    public function testThrowsWhenForeignKeyDisagreesWithTheRelation(): void
    {
        $child = new class extends TaskResource {
            public function parent(): ?ParentRelation
            {
                return ParentRelation::make(ProjectResource::class)
                    ->relationship('tasks')->foreignKey('wrong_id');
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/foreignKey/');
        $this->resolver(new ProjectResource(), $child)->resolve($child);
    }
}
```

- [ ] **Step 4: Run it, expect failure**

Run: `vendor/bin/phpunit tests/Relation/ParentRelationResolverTest.php`
Expected: FAIL — `ParentRelationResolver`/`ResolvedParentRelation` not found.

- [ ] **Step 5: Implement `ResolvedParentRelation`**

`src/Relation/ResolvedParentRelation.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;

/**
 * @internal immutable, validated view of a child resource's {@see ParentRelation}:
 * the resolved parent resource, the parent {@see Relation} that holds these
 * children, the child→parent foreign-key column, and the attribute used to title
 * the parent record in the breadcrumb.
 */
final readonly class ResolvedParentRelation
{
    public function __construct(
        public AdminResource $parentResource,
        public Relation $relation,
        public string $foreignKey,
        public string $recordTitleAttribute,
    ) {
    }
}
```

- [ ] **Step 6: Implement `ParentRelationResolver`**

`src/Relation/ParentRelationResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;

/**
 * @internal validates and resolves a child resource's {@see AdminResource::parent()}
 * declaration on first use (REL-20, lazy — mirroring {@see RelationResolver} and the
 * controller's slug-collision assertion; no compiler pass). Asserts: the parent is
 * registered, it declares the named relationship, that relation is one-to-many, and
 * its foreignKey equals the child's declared foreignKey.
 */
final readonly class ParentRelationResolver
{
    public function __construct(private ResourceRegistry $registry)
    {
    }

    public function resolve(AdminResource $child): ResolvedParentRelation
    {
        $parentRelation = $child->parent();
        if (null === $parentRelation) {
            throw new \LogicException(\sprintf('Resource "%s" is not nested: parent() returns null.', $child->getSlug()));
        }

        $parentClass = $parentRelation->getParentClass();
        try {
            $parent = $this->registry->getByClass($parentClass);
        } catch (\Throwable $e) {
            throw new \LogicException(\sprintf('Nested resource "%s" names parent "%s" which is not registered in the panel.', $child->getSlug(), $parentClass), 0, $e);
        }

        $name = $parentRelation->getRelationship();
        $relation = null;
        foreach ($parent->relations() as $candidate) {
            if ($candidate->getName() === $name) {
                $relation = $candidate;
                break;
            }
        }
        if (null === $relation) {
            throw new \LogicException(\sprintf('Nested resource "%s" names parent relationship "%s", but "%s" declares no relation "%s".', $child->getSlug(), $name, $parent->getSlug(), $name));
        }

        if (RelationKind::OneToMany !== $relation->getKind()) {
            throw new \LogicException(\sprintf('Nested resource "%s" must hang off a one-to-many parent relation; "%s.%s" is not one-to-many.', $child->getSlug(), $parent->getSlug(), $name));
        }

        $foreignKey = $parentRelation->getForeignKey();
        if ($relation->getForeignKey() !== $foreignKey) {
            throw new \LogicException(\sprintf('Nested resource "%s" foreignKey "%s" disagrees with parent relation "%s.%s" foreignKey "%s".', $child->getSlug(), $foreignKey, $parent->getSlug(), $name, (string) $relation->getForeignKey()));
        }

        return new ResolvedParentRelation(
            parentResource: $parent,
            relation: $relation,
            foreignKey: $foreignKey,
            recordTitleAttribute: $parentRelation->getRecordTitle() ?? $parent->getIdentifierField(),
        );
    }
}
```

Add `use Atrium\Relation\RelationKind;`? No — same namespace, so reference `RelationKind` directly (already in `Atrium\Relation`).

- [ ] **Step 7: Run the resolver test, expect pass**

Run: `vendor/bin/phpunit tests/Relation/ParentRelationResolverTest.php` → PASS.

- [ ] **Step 8: Seed `SampleData` with Project/Task**

In `tests/Fixtures/Data/SampleData.php`: add `use` for `Project` and `Task`; add private arrays + constructor seeding; expose them through `provider()`.

```php
    /** @var list<Project> */
    private array $projects;

    /** @var list<Task> */
    private array $tasks;
```

In the constructor (after the comments seeding):

```php
        // Nested-resource fixtures: Tasks belong to a Project via Task.projectId.
        $this->projects = [
            new Project(1, 'Alpha'),
            new Project(2, 'Beta'),
        ];
        $this->tasks = [
            new Task(1, 'Design', projectId: 1),
            new Task(2, 'Build', projectId: 1),
            new Task(3, 'Ship', projectId: 2),
        ];
```

In `provider()` extend the map:

```php
        return new ArrayDataProvider([
            Tag::class => $this->tags,
            Post::class => $this->posts,
            Comment::class => $this->comments,
            Project::class => $this->projects,
            Task::class => $this->tasks,
        ]);
```

Extend `relationProvider()`'s record map so the Project→Tasks relation manager can list (used in Task 9):

```php
            [
                Comment::class => $this->comments,
                Tag::class => $this->tags,
                Task::class => $this->tasks,
            ],
```

- [ ] **Step 9: Register the resources + bump the boot count**

In `tests/Functional/AtriumTestKernel.php`, alongside the other `$services->set(...)` resource registrations:

```php
        $services->set(ProjectResource::class)
            ->autowire()->autoconfigure();
        $services->set(TaskResource::class)
            ->autowire()->autoconfigure();
```

(match the exact `->set(...)` chain the neighbouring fixtures use; add the two `use` imports.)

In `tests/Functional/KernelBootTest.php`, change the asserted resource count from `28` to `30`.

- [ ] **Step 10: Run gates**

Run: `composer test && composer phpstan && composer cs` → all green (resolver unit tests + boot count updated).

- [ ] **Step 11: Commit**

```bash
git add src/Relation/ResolvedParentRelation.php src/Relation/ParentRelationResolver.php \
        tests/Fixtures/Entity/Project.php tests/Fixtures/Entity/Task.php \
        tests/Fixtures/Resource/ProjectResource.php tests/Fixtures/Resource/TaskResource.php \
        tests/Fixtures/Data/SampleData.php tests/Functional/AtriumTestKernel.php \
        tests/Functional/KernelBootTest.php tests/Relation/ParentRelationResolverTest.php
git commit -m "Lazily resolve + validate nested parent() declarations (REL-14, REL-20)"
```

---

## Task 3: `NestedActionContext` (5-segment URLs)

**Files:**
- Modify: `src/Action/ActionContext.php` (lift `final`)
- Create: `src/Action/NestedActionContext.php`
- Test: `tests/Action/NestedActionContextTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Action/NestedActionContextTest.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Action;

use Atrium\Action\ActionContext;
use Atrium\Action\NestedActionContext;
use PHPUnit\Framework\TestCase;

final class NestedActionContextTest extends TestCase
{
    public function testPrependsTheParentSegmentToEveryRecordUrl(): void
    {
        $context = new NestedActionContext('/admin', 'project', '1', 'task', '7');

        self::assertSame('/admin/project/1/task', $context->resourceUrl());
        self::assertSame('/admin/project/1/task/7', $context->recordRootUrl());
        self::assertSame('/admin/project/1/task/7/edit', $context->recordUrl('edit'));
    }

    public function testIsAnActionContext(): void
    {
        self::assertInstanceOf(ActionContext::class, new NestedActionContext('/admin', 'project', '1', 'task', '7'));
    }

    public function testParentIdIsUrlEncoded(): void
    {
        $context = new NestedActionContext('/admin', 'project', 'a b', 'task', '7');
        self::assertSame('/admin/project/a%20b/task', $context->resourceUrl());
    }
}
```

- [ ] **Step 2: Run it, expect failure**

Run: `vendor/bin/phpunit tests/Action/NestedActionContextTest.php`
Expected: FAIL — `NestedActionContext` not found (and, once created, a "cannot extend final class" error until Step 3).

- [ ] **Step 3: Lift `final` on `ActionContext`**

In `src/Action/ActionContext.php`, change the class line from:

```php
final readonly class ActionContext
```

to:

```php
readonly class ActionContext
```

and extend the class docblock with a sentence:

```php
 * Doctrine-agnostic so actions never reach into the router or the entity. Not
 * `final`: {@see NestedActionContext} extends it to prepend a parent segment for
 * nested resources (REL-16).
```

- [ ] **Step 4: Implement `NestedActionContext`**

`src/Action/NestedActionContext.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Action;

/**
 * An {@see ActionContext} for a nested resource (REL-16): it prepends the
 * `{parentResource}/{parentId}` segment to the resource URL, so every per-record
 * URL an {@see Action} builds (`recordUrl`, `recordRootUrl`) and every `rowUrl`
 * becomes the 5-segment nested form — `/admin/{parent}/{pid}/{resource}/{id}/...`
 * — without any action needing to know it is nested.
 */
final readonly class NestedActionContext extends ActionContext
{
    public function __construct(
        string $pathPrefix,
        public string $parentSlug,
        public string $parentId,
        string $slug,
        string $recordId,
    ) {
        parent::__construct($pathPrefix, $slug, $recordId);
    }

    public function resourceUrl(): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$this->parentSlug.'/'.rawurlencode($this->parentId).'/'.$this->slug;
    }
}
```

- [ ] **Step 5: Run the test + gates**

Run: `vendor/bin/phpunit tests/Action/NestedActionContextTest.php` → PASS.
Run: `composer test && composer phpstan && composer cs` → green.

- [ ] **Step 6: Commit**

```bash
git add src/Action/ActionContext.php src/Action/NestedActionContext.php tests/Action/NestedActionContextTest.php
git commit -m "NestedActionContext: 5-segment record URLs for nested resources (REL-16)"
```

---

## Task 4: nested route family + non-collision

**Files:**
- Modify: `config/routes.php`
- Test: `tests/Functional/NestedRoutingTest.php`

- [ ] **Step 1: Write the failing routing test**

`tests/Functional/NestedRoutingTest.php` — boot the kernel, pull the router, assert each nested path matches the intended route/controller and that flat paths are unaffected.

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class NestedRoutingTest extends KernelTestCase
{
    /** @return array{_route: string, _controller: string} */
    private function match(string $path): array
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        /** @var array{_route: string, _controller: string} $m */
        $m = $router->match($path);

        return $m;
    }

    public function testNestedIndexMatches(): void
    {
        $m = $this->match('/admin/project/1/task');
        self::assertSame('atrium_nested_index', $m['_route']);
    }

    public function testNestedCreateMatchesBeforeBareId(): void
    {
        $m = $this->match('/admin/project/1/task/new');
        self::assertSame('atrium_nested_create', $m['_route']);
    }

    public function testNestedEditMatches(): void
    {
        $m = $this->match('/admin/project/1/task/7/edit');
        self::assertSame('atrium_nested_edit', $m['_route']);
    }

    public function testNestedViewMatches(): void
    {
        $m = $this->match('/admin/project/1/task/7');
        self::assertSame('atrium_nested_view', $m['_route']);
    }

    public function testFlatRoutesAreUnaffected(): void
    {
        self::assertSame('atrium_resource_view', $this->match('/admin/project/1')['_route']);
        self::assertSame('atrium_resource_edit', $this->match('/admin/project/1/edit')['_route']);
        self::assertSame('atrium_resource_create', $this->match('/admin/project/new')['_route']);
        self::assertSame('atrium_page', $this->match('/admin/project')['_route']);
    }
}
```

- [ ] **Step 2: Run it, expect failure**

Run: `vendor/bin/phpunit tests/Functional/NestedRoutingTest.php`
Expected: FAIL — nested paths currently match `atrium_page`/`atrium_resource_*` or 404; `atrium_nested_*` routes don't exist.

- [ ] **Step 3: Add the nested route family**

In `config/routes.php`, insert **after** the `atrium_resource_view` block and **before** `atrium_page`:

```php
    // Nested resources (REL-15): a parent segment scopes a child resource. The
    // literal variants (/new, /{id}/edit) are declared before the bare /{id} and
    // the index so the literals win — the flat-route ordering rule, one level
    // deeper. A path with 3+ segments after the prefix cannot match any flat route
    // (those have at most 3 placeholders in fixed literal positions; {id} is [^/]+
    // so it never spans a '/'), so the two families never shadow each other.
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

The four controller methods don't exist yet — the routing test only matches routes, but the kernel may validate controllers on match in some setups. If `match()` throws on the missing controller, add temporary stub methods in Task 5 first; otherwise proceed (route matching does not instantiate the controller). Expected here: the test passes on route names alone.

- [ ] **Step 4: Run the test, expect pass; run gates**

Run: `vendor/bin/phpunit tests/Functional/NestedRoutingTest.php` → PASS.
Run: `composer test && composer phpstan && composer cs` → green. (Other suites unaffected.)

- [ ] **Step 5: Commit**

```bash
git add config/routes.php tests/Functional/NestedRoutingTest.php
git commit -m "Nested route family with literals-win ordering (REL-15)"
```

---

## Task 5: controller nested resolution + `nestedView`

**Files:**
- Modify: `src/Controller/AdminController.php` (inject resolver; add `resolveNested()` + `nestedView()`; stub the other three to satisfy routing until Tasks 6–7)
- Modify: `config/services.php` (register `ParentRelationResolver` if needed)
- Test: `tests/Functional/NestedResourceTest.php`

- [ ] **Step 1: Write the failing functional test (view + 404 paths)**

`tests/Functional/NestedResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NestedResourceTest extends WebTestCase
{
    public function testNestedViewRendersAChildScopedToItsParent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/1/task/1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Design'); // task 1's title
    }

    public function testCrossParentViewIs404(): void
    {
        $client = static::createClient();
        // Task 1 belongs to project 1; requesting it under project 2 must 404.
        $client->request('GET', '/admin/project/2/task/1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownParentRecordIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/999/task/1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUndeclaredNestingIs404(): void
    {
        $client = static::createClient();
        // 'tag-rel' is a registered resource but is NOT nested under 'project'.
        $client->request('GET', '/admin/project/1/tag-rel/1');

        self::assertResponseStatusCodeSame(404);
    }
}
```

> Confirm `tag-rel` is registered and not nested (it is, from REL-M3). If its id space doesn't include `1`, the undeclared-nesting branch must 404 **before** any record lookup, so the test still holds.

- [ ] **Step 2: Run it, expect failure**

Run: `vendor/bin/phpunit tests/Functional/NestedResourceTest.php`
Expected: FAIL — `nestedView` not callable / 500 (controller method missing or stubbed).

- [ ] **Step 3: Inject the resolver + property accessor**

In `src/Controller/AdminController.php`, add constructor params (Task 8 also uses the accessor for breadcrumb titles — add it now to avoid a second ctor edit):

```php
use Atrium\Relation\ParentRelationResolver;
use Atrium\Relation\ResolvedParentRelation;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
```

```php
    public function __construct(
        private Environment $twig,
        private ResourceRegistry $registry,
        private DashboardRegistry $dashboards,
        private string $brand,
        private string $pathPrefix,
        private ParentRelationResolver $parentResolver,
        private PropertyAccessorInterface $accessor,
        private ?DataProviderInterface $dataProvider = null,
    ) {
    }
```

**`AdminController` is wired explicitly and positionally** (`config/services.php:41-50`) — NOT autowired. You MUST update its `->args([...])` to match the new constructor order, inserting the two new services **before** the nullable `DataProviderInterface` (which stays last). Replace the block at `config/services.php:41-50` with:

```php
    $services->set(AdminController::class)
        ->args([
            service('twig'),
            service(ResourceRegistry::class),
            service(DashboardRegistry::class),
            param('atrium.brand'),
            param('atrium.path_prefix'),
            service(ParentRelationResolver::class),
            service('property_accessor'),
            service(DataProviderInterface::class)->ignoreOnInvalid(),
        ])
        ->tag('controller.service_arguments');
```

And register `ParentRelationResolver` as a service right after the `RelationResolver` registration (`config/services.php:52-53`):

```php
    $services->set(ParentRelationResolver::class)
        ->args([service(ResourceRegistry::class)]);
```

Add `use Atrium\Relation\ParentRelationResolver;` to `config/services.php`'s imports. (`property_accessor` is the framework's `PropertyAccessorInterface` service.) Arg order in the `->args([...])` list must exactly mirror the constructor parameter order, or the container will pass services into the wrong slots and fail with a type error on first boot.

- [ ] **Step 4: Add the shared resolution helper + `nestedView`**

Add to `AdminController` (mirrors `view()` plus parent scoping):

```php
    /**
     * Resolve a nested request: the parent resource + its (scoped) record and the
     * child resource, asserting the URL's nesting is the one the child declares.
     * Any failure — unregistered/forbidden resource, undeclared nesting, or an
     * out-of-scope parent — is a 404/403, never a leak.
     *
     * @return array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation}
     */
    private function resolveNested(string $parentResource, string $parentId, string $resource): array
    {
        $parent = $this->requireResource($parentResource);
        $child = $this->requireResource($resource);
        $this->denyUnless($parent->canAccess() && $child->canAccess());

        // The child must declare it is nested under exactly this parent + relation.
        // A mismatch (or a non-nested child) is a 404: this URL shape isn't offered.
        if (null === $child->parent()) {
            throw new NotFoundHttpException(\sprintf('Resource "%s" is not a nested resource.', $resource));
        }
        $resolved = $this->parentResolver->resolve($child);
        if ($resolved->parentResource->getSlug() !== $parent->getSlug()) {
            throw new NotFoundHttpException(\sprintf('Resource "%s" is not nested under "%s".', $resource, $parentResource));
        }

        $parentRecord = null;
        if (null !== $this->dataProvider) {
            $parentRecord = $this->dataProvider->find(
                $parent->getEntityClass(),
                $parentId,
                $parent->scopeFilters(),
                $parent->getIdentifierField(),
            );
            if (null === $parentRecord) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $parentResource, $parentId));
            }
        }

        return ['parent' => $parent, 'parentRecord' => $parentRecord, 'child' => $child, 'resolved' => $resolved];
    }

    /**
     * Resolve the child record scoped to BOTH its own scopeQuery and the parent FK
     * (a child of another parent — a forged id — is a 404, never reachable here).
     */
    private function findNestedRecord(AdminResource $child, ResolvedParentRelation $resolved, string $parentId, string $id): ?object
    {
        if (null === $this->dataProvider) {
            return null;
        }

        $filters = $child->scopeFilters() + [$resolved->foreignKey => $parentId];

        return $this->dataProvider->find($child->getEntityClass(), $id, $filters, $child->getIdentifierField());
    }

    public function nestedView(string $parentResource, string $parentId, string $resource, string $id): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];

        $page = $child->resolvePage('view');
        if (null === $page) {
            throw new NotFoundHttpException(\sprintf('The "%s" resource has no view screen.', $resource));
        }

        $record = $this->findNestedRecord($child, $ctx['resolved'], $parentId, $id);
        if (null !== $this->dataProvider) {
            if (null === $record) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
            }
            $this->denyUnless($child->canView($record));
        }

        $context = $this->nestedPageContext($ctx, $id);

        return $this->render('@Atrium/admin/view_page.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page->getHeading($context),
            'subheading' => $page->getSubheading($context),
            'schema' => $child->resolveViewSchema(),
            'headerWidgets' => $child->resolveViewHeaderWidgets($context),
            'footerWidgets' => $child->resolveViewFooterWidgets($context),
            'record' => $record,
            'entityId' => $id,
            // Nested chrome (Task 8 consumes these; harmless now).
            'parentResourceSlug' => $parentResource,
            'parentRecordId' => $parentId,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'backUrl' => $this->nestedIndexUrl($ctx, $parentId),
        ]);
    }
```

Add three helpers used above (full versions land in Task 8; provide minimal now):

```php
    /** @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx */
    private function nestedPageContext(array $ctx, ?string $entityId): PageContext
    {
        // Task 8 enriches PageContext with parent records + nestedUrl(); for now a
        // plain context over the child slug keeps Page hooks working.
        return new PageContext(
            $ctx['child']->getSlug(),
            $this->pathPrefix,
            $entityId,
            $ctx['child']->getSingularLabel(),
            $ctx['child']->getLabel(),
        );
    }

    /** @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx */
    private function nestedIndexUrl(array $ctx, string $parentId): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$ctx['parent']->getSlug().'/'.rawurlencode($parentId).'/'.$ctx['child']->getSlug();
    }

    /**
     * @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx
     *
     * @return list<array{label: string, url: ?string}>
     */
    private function nestedBreadcrumbs(array $ctx): array
    {
        return []; // Task 8 builds the real trail.
    }
```

Add a **stub** for the other three nested actions so routing/Step-3 of Task 4 stays green until Tasks 6–7 fill them:

```php
    public function nestedCreate(string $parentResource, string $parentId, string $resource): Response
    {
        throw new NotFoundHttpException('Not implemented yet.'); // Task 7
    }

    public function nestedEdit(string $parentResource, string $parentId, string $resource, string $id): Response
    {
        throw new NotFoundHttpException('Not implemented yet.'); // Task 6
    }

    public function nestedIndex(string $parentResource, string $parentId, string $resource): Response
    {
        throw new NotFoundHttpException('Not implemented yet.'); // Task 7
    }
```

- [ ] **Step 5: Run the test, expect pass; run gates**

Run: `vendor/bin/phpunit tests/Functional/NestedResourceTest.php` → PASS (view + all three 404 branches).
Run: `composer test && composer phpstan && composer cs` → green.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/AdminController.php config/services.php tests/Functional/NestedResourceTest.php
git commit -m "Nested view: scoped parent + child resolution, cross-parent 404 (REL-15, REL-17)"
```

---

## Task 6: `nestedEdit`

**Files:**
- Modify: `src/Controller/AdminController.php` (replace `nestedEdit` stub)
- Modify: `templates/admin/form_page.html.twig` (pass `parentRecordId` to `RelationManagers`; nested Back link — minimal now, breadcrumb in Task 8)
- Test: extend `tests/Functional/NestedResourceTest.php`

- [ ] **Step 1: Add failing edit tests**

Append to `tests/Functional/NestedResourceTest.php`:

```php
    public function testNestedEditRendersScopedRecord(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/1/task/2/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testCrossParentEditIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/2/task/1/edit'); // task 1 is under project 1

        self::assertResponseStatusCodeSame(404);
    }
```

- [ ] **Step 2: Run, expect failure**

Run: `vendor/bin/phpunit tests/Functional/NestedResourceTest.php` → the two new tests FAIL (stub throws 404 for the valid edit; the valid case expects 200).

- [ ] **Step 3: Implement `nestedEdit`** (mirrors `edit()` + parent scoping + nested redirect)

```php
    public function nestedEdit(string $parentResource, string $parentId, string $resource, string $id): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];

        $record = $this->findNestedRecord($child, $ctx['resolved'], $parentId, $id);
        if (null !== $this->dataProvider) {
            if (null === $record) {
                throw new NotFoundHttpException(\sprintf('No %s found for id "%s".', $resource, $id));
            }
            $this->denyUnless($child->canEdit($record));
        }

        $page = $child->resolvePage('edit');
        $context = $this->nestedPageContext($ctx, $id);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page?->getHeading($context) ?? 'Edit '.$child->getSingularLabel(),
            'subheading' => $page?->getSubheading($context),
            'entityId' => $id,
            // After saving the child, return to its nested index under this parent.
            'redirectUrl' => $page?->getRedirectUrl($context) ?? $this->nestedIndexUrl($ctx, $parentId),
            'presetValues' => [],
            'parentResourceSlug' => $parentResource,
            'parentRecordId' => $parentId,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'backUrl' => $this->nestedIndexUrl($ctx, $parentId),
        ]);
    }
```

> The flat `edit()`/`create()` pass no `presetValues`/`parentResourceSlug`/`backUrl`. To keep one template, make `form_page.html.twig` default these via Twig `|default`. The flat `create()` currently passes `redirectUrl` from the page; keep that. The flat `edit()` currently does **not** pass `presetValues` — the template defaults it to `[]`.

- [ ] **Step 4: Make `form_page.html.twig` nested-aware (minimal)**

Edit `templates/admin/form_page.html.twig`:
- Default the new vars at the top of the body: `{% set parentRecordId = parentRecordId|default(null) %}`, `{% set presetValues = presetValues|default({}) %}`, `{% set backUrl = backUrl|default(panel.pathPrefix ~ '/' ~ resource.slug) %}`.
- Change the Back link `href` from `{{ panel.pathPrefix }}/{{ resource.slug }}` to `{{ backUrl }}`.
- Pass preset values to the form component: add `presetValues: presetValues` to the `component(resource.formComponentName, {...})` props map.
- The relation-managers band stays keyed on `entityId` (the child record) — unchanged.

- [ ] **Step 5: Run tests, expect pass; gates**

Run: `vendor/bin/phpunit tests/Functional/NestedResourceTest.php` → PASS.
Run: `composer test && composer phpstan && composer cs` → green (flat form pages still render — the `|default`s cover them; verify `PagesTest`/`FormComponentTest` stay green).

- [ ] **Step 6: Commit**

```bash
git add src/Controller/AdminController.php templates/admin/form_page.html.twig tests/Functional/NestedResourceTest.php
git commit -m "Nested edit: scoped record, nested redirect + Back link (REL-15)"
```

---

## Task 7: `nestedCreate` + `nestedIndex` + DataTable nested mode

**Files:**
- Modify: `src/Twig/Components/AbstractRecordTable.php` (`extraFilters()` seam)
- Modify: `src/Twig/Components/DataTable.php` (`parentId` prop + nested `actionContext()`/`extraFilters()`)
- Modify: `src/Controller/AdminController.php` (replace `nestedCreate`/`nestedIndex` stubs)
- Modify: `templates/admin/resource.html.twig` (pass `parentId` to the DataTable when nested)
- Test: extend `tests/Functional/NestedResourceTest.php`

- [ ] **Step 1: Add the `extraFilters()` seam to `AbstractRecordTable`**

Add a protected hook and merge it into both query builders. After the class's existing methods, add:

```php
    /**
     * Always-on equality conditions layered under the configured filters (e.g. a
     * nested table's parent foreign key). Empty by default; a subclass narrows the
     * whole table. Merged after {@see resolvedFilters()} so a forged filter value
     * can never override a structural scope.
     *
     * @return array<string, scalar|bool|null>
     */
    protected function extraFilters(): array
    {
        return [];
    }
```

In `query()` change `filters: $this->resolvedFilters()` to `filters: $this->resolvedFilters() + $this->extraFilters()`. Wait — array union keeps the **left** on key clash; the structural scope must win, so use `$this->extraFilters() + $this->resolvedFilters()`. Apply the same to `allMatchingQuery()`.

> Rationale: `extraFilters()` is trusted (the parent FK from the URL, already validated by the controller's parent-record resolution); a user-supplied filter on the same field must not relax it, so `extraFilters()` is the left operand and wins.

- [ ] **Step 2: Write the failing nested-list/create tests**

Append to `tests/Functional/NestedResourceTest.php`:

```php
    public function testNestedIndexListsOnlyThisParentsChildren(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1/task');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Design', $body);  // project 1
        self::assertStringContainsString('Build', $body);   // project 1
        self::assertStringNotContainsString('Ship', $body); // project 2 — excluded
    }

    public function testNestedIndexRowLinksAreFiveSegment(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1/task');

        self::assertResponseIsSuccessful();
        // A row link to a task view must carry the parent segment.
        self::assertStringContainsString('/admin/project/1/task/1', $crawler->html());
    }

    public function testNestedCreateRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/1/task/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
```

- [ ] **Step 3: Run, expect failure**

Run: `vendor/bin/phpunit tests/Functional/NestedResourceTest.php` → the three new tests FAIL (stubs throw 404; list scope/URLs not wired).

- [ ] **Step 4: Give `DataTable` a nested mode**

In `src/Twig/Components/DataTable.php`:
- Add a `parentId` LiveProp + mount param:

```php
    /** When set, the table is the nested index of a child under this parent id. */
    #[LiveProp]
    public ?string $parentId = null;
```

```php
    public function mount(string $resource, string $pathPrefix = '', ?int $perPage = null, ?string $parentId = null): void
    {
        // ... existing assignments ...
        $this->parentId = $parentId;
    }
```

- Inject `ParentRelationResolver` into the constructor **before** `DataWriterInterface $writer`/`PropertyAccessorInterface $accessor` (those two are forwarded to `parent::__construct($writer, $accessor)` — a param after them would land in the wrong slot). `DataTable` is autowired (`config/services.php:55` `->load('Atrium\\Twig\\Components\\', ...)`), so no services.php change is needed. The full constructor:

```php
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly ParentRelationResolver $parentResolver,
        DataWriterInterface $writer,
        PropertyAccessorInterface $accessor,
    ) {
        parent::__construct($writer, $accessor);
    }
```

Then resolve nested context lazily:

```php
    private ?ResolvedParentRelation $resolvedParent = null;

    private function nestedParent(): ?ResolvedParentRelation
    {
        if (null === $this->parentId || '' === $this->parentId) {
            return null;
        }

        return $this->resolvedParent ??= $this->parentResolver->resolve($this->resource());
    }
```

- Override the two seams:

```php
    protected function extraFilters(): array
    {
        $parent = $this->nestedParent();

        return null === $parent ? [] : [$parent->foreignKey => $this->parentId];
    }

    protected function actionContext(?string $id): ActionContext
    {
        $parent = $this->nestedParent();
        if (null === $parent) {
            return new ActionContext($this->pathPrefix, $this->resource, $id ?? '');
        }

        return new NestedActionContext(
            $this->pathPrefix,
            $parent->parentResource->getSlug(),
            (string) $this->parentId,
            $this->resource,
            $id ?? '',
        );
    }
```

Add imports `use Atrium\Action\NestedActionContext;`, `use Atrium\Relation\ParentRelationResolver;`, `use Atrium\Relation\ResolvedParentRelation;`.

> `rowUrl()` is unchanged — it already calls `$context->recordRootUrl()`/`recordUrl('edit')`, which are now 5-segment because `actionContext()` returns a `NestedActionContext`. Same for Edit/View/Delete record actions.

- [ ] **Step 5: Implement `nestedIndex` + `nestedCreate`**

Replace the stubs in `AdminController`:

```php
    public function nestedIndex(string $parentResource, string $parentId, string $resource): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];
        $this->denyUnless($child->canViewAny());

        $page = $child->resolvePage('index');
        $context = $this->nestedPageContext($ctx, null);

        return $this->render('@Atrium/admin/resource.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page?->getHeading($context) ?? $child->getLabel(),
            'subheading' => $page?->getSubheading($context),
            'headerWidgets' => $child->resolveHeaderWidgets($context),
            'footerWidgets' => $child->resolveFooterWidgets($context),
            'parentRecordId' => $parentId,           // flips the DataTable into nested mode
            'parentResourceSlug' => $parentResource,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'createUrl' => $this->nestedIndexUrl($ctx, $parentId).'/new',
        ]);
    }

    public function nestedCreate(string $parentResource, string $parentId, string $resource): Response
    {
        $ctx = $this->resolveNested($parentResource, $parentId, $resource);
        $child = $ctx['child'];
        $this->denyUnless($child->canCreate());

        $page = $child->resolvePage('create');
        $context = $this->nestedPageContext($ctx, null);

        return $this->render('@Atrium/admin/form_page.html.twig', [
            'panel' => $this->panel($parentResource),
            'resource' => $child,
            'heading' => $page?->getHeading($context) ?? 'New '.$child->getSingularLabel(),
            'subheading' => $page?->getSubheading($context),
            'entityId' => null,
            'redirectUrl' => $page?->getRedirectUrl($context) ?? $this->nestedIndexUrl($ctx, $parentId),
            // Preset the FK so the created child is linked to this parent in one save.
            // Read the parent's *typed* id from the resolved record (not the raw URL
            // string) so PropertyAccess can set an int-typed FK (e.g. Task.projectId).
            'presetValues' => [$ctx['resolved']->foreignKey => $this->parentScalarId($ctx)],
            'parentResourceSlug' => $parentResource,
            'parentRecordId' => $parentId,
            'breadcrumbs' => $this->nestedBreadcrumbs($ctx),
            'backUrl' => $this->nestedIndexUrl($ctx, $parentId),
        ]);
    }

    /**
     * The parent's identifier as a scalar of its *native* type (int/string), read
     * from the already-resolved parent record so a preset FK matches the child
     * property's type. Null when no data provider resolved a parent (no-DB install).
     *
     * @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx
     */
    private function parentScalarId(array $ctx): int|string|null
    {
        if (null === $ctx['parentRecord']) {
            return null;
        }

        $id = $this->accessor->getValue($ctx['parentRecord'], $ctx['parent']->getIdentifierField());

        return \is_int($id) || \is_string($id) ? $id : null;
    }
```

- [ ] **Step 6: Pass `parentId` to the DataTable in `resource.html.twig`**

In `templates/admin/resource.html.twig`, default the var and pass it:

```twig
{% set parentRecordId = parentRecordId|default(null) %}
...
<twig:Atrium:DataTable :resource="resource.slug" :pathPrefix="panel.pathPrefix" :parentId="parentRecordId" />
```

A flat list passes `null` (default) → DataTable stays flat. (If the list template renders a "New" header link/button derived from `createUrl`, also default `createUrl` and prefer it when set; otherwise the existing header-action wiring is unchanged.)

- [ ] **Step 7: Run tests, expect pass; gates**

Run: `vendor/bin/phpunit tests/Functional/NestedResourceTest.php` → PASS (list scoped to parent 1, 5-segment row URL, create renders).
Run: `composer test && composer phpstan && composer cs` → green. Pay attention to `DataTableComponentTest`/`DataTableSortPaginationTest` — flat tables must be unaffected (the `parentId` default is null).

- [ ] **Step 8: Commit**

```bash
git add src/Twig/Components/AbstractRecordTable.php src/Twig/Components/DataTable.php \
        src/Controller/AdminController.php templates/admin/resource.html.twig \
        tests/Functional/NestedResourceTest.php
git commit -m "Nested index + create: parent-scoped DataTable, preset FK on create (REL-15, REL-16)"
```

---

## Task 8: `PageContext` ancestry + recursive breadcrumb

**Files:**
- Modify: `src/Page/PageContext.php` (add `parentRecords` + `nestedUrl()`)
- Create: `templates/admin/_breadcrumb.html.twig`
- Modify: `src/Controller/AdminController.php` (`nestedBreadcrumbs()` real impl; pass ancestry into `nestedPageContext`)
- Modify: `templates/admin/{form_page,view_page,resource}.html.twig` (include the breadcrumb)
- Test: `tests/Functional/NestedBreadcrumbTest.php`, extend `tests/Page/PageContextTest.php` (if present) or add `tests/Page/PageContextNestedTest.php`

- [ ] **Step 1: Write the failing `PageContext` unit test**

`tests/Page/PageContextNestedTest.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Page;

use Atrium\Page\PageContext;
use PHPUnit\Framework\TestCase;

final class PageContextNestedTest extends TestCase
{
    public function testFlatContextHasNoParentRecords(): void
    {
        $context = new PageContext('task', '/admin', '7', 'Task', 'Tasks');
        self::assertSame([], $context->parentRecords);
    }

    public function testNestedUrlBuildsTheFiveSegmentForm(): void
    {
        $context = new PageContext(
            'task', '/admin', '7', 'Task', 'Tasks',
            parentResourceSlug: 'project', parentRecordId: '1', parentRecords: [(object) ['id' => 1]],
        );

        self::assertSame('/admin/project/1/task', $context->nestedUrl('index'));
        self::assertSame('/admin/project/1/task/new', $context->nestedUrl('create'));
        self::assertSame('/admin/project/1/task/7/edit', $context->nestedUrl('edit', '7'));
        self::assertSame('/admin/project/1/task/7', $context->nestedUrl('view', '7'));
    }
}
```

- [ ] **Step 2: Run, expect failure**

Run: `vendor/bin/phpunit tests/Page/PageContextNestedTest.php` → FAIL (unknown ctor args / `nestedUrl`).

- [ ] **Step 3: Extend `PageContext`**

`src/Page/PageContext.php` — add optional nested fields + `nestedUrl()`:

```php
    /**
     * @param list<object> $parentRecords resolved ancestry, nearest last (empty when not nested)
     */
    public function __construct(
        public string $resourceSlug,
        public string $pathPrefix,
        public ?string $entityId = null,
        public string $singularLabel = '',
        public string $pluralLabel = '',
        public ?string $parentResourceSlug = null,
        public ?string $parentRecordId = null,
        public array $parentRecords = [],
    ) {
    }
```

Add the nested URL builder (the route shape carries a single parent segment):

```php
    /**
     * A nested URL for this resource under its parent segment. `index` and `create`
     * take no id; `edit`/`view` take the record id. Returns the flat URL when the
     * context is not nested.
     */
    public function nestedUrl(string $action, ?string $id = null): string
    {
        if (null === $this->parentResourceSlug || null === $this->parentRecordId) {
            return match ($action) {
                'index' => $this->indexUrl(),
                'create' => $this->createUrl(),
                'edit' => $this->editUrl((string) $id),
                default => $this->viewUrl((string) $id),
            };
        }

        $base = rtrim($this->pathPrefix, '/').'/'.$this->parentResourceSlug.'/'.rawurlencode($this->parentRecordId).'/'.$this->resourceSlug;

        return match ($action) {
            'index' => $base,
            'create' => $base.'/new',
            'edit' => $base.'/'.(string) $id.'/edit',
            default => $base.'/'.(string) $id,
        };
    }
```

- [ ] **Step 4: Run the unit test, expect pass**

Run: `vendor/bin/phpunit tests/Page/PageContextNestedTest.php` → PASS.

- [ ] **Step 5: Build the breadcrumb in the controller**

Replace `nestedBreadcrumbs()` and enrich `nestedPageContext()`:

```php
    /** @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx */
    private function nestedPageContext(array $ctx, ?string $entityId): PageContext
    {
        $parentRecords = null !== $ctx['parentRecord'] ? [$ctx['parentRecord']] : [];

        return new PageContext(
            $ctx['child']->getSlug(),
            $this->pathPrefix,
            $entityId,
            $ctx['child']->getSingularLabel(),
            $ctx['child']->getLabel(),
            parentResourceSlug: $ctx['parent']->getSlug(),
            parentRecordId: null !== $ctx['parentRecord'] ? $this->stringId($ctx['parent'], $ctx['parentRecord']) : null,
            parentRecords: $parentRecords,
        );
    }

    /**
     * Breadcrumb trail for a nested page: the parent resource index, the parent
     * record (titled via the ParentRelation's recordTitle / the parent identifier),
     * then the child resource index. The current record is the page <h1>, not a crumb.
     *
     * @param array{parent: AdminResource, parentRecord: ?object, child: AdminResource, resolved: ResolvedParentRelation} $ctx
     *
     * @return list<array{label: string, url: ?string}>
     */
    private function nestedBreadcrumbs(array $ctx): array
    {
        $parent = $ctx['parent'];
        $base = rtrim($this->pathPrefix, '/');
        $crumbs = [
            ['label' => $parent->getLabel(), 'url' => $base.'/'.$parent->getSlug()],
        ];

        $record = $ctx['parentRecord'];
        if (null !== $record) {
            $parentId = $this->stringId($parent, $record);
            $titleValue = $this->accessor->getValue($record, $ctx['resolved']->recordTitleAttribute);
            $title = \is_scalar($titleValue) ? (string) $titleValue : (string) $parentId;
            // Link to the parent's view page if it has one, else its edit page.
            $suffix = null !== $parent->resolvePage('view') ? '' : '/edit';
            $crumbs[] = [
                'label' => $title,
                'url' => $base.'/'.$parent->getSlug().'/'.rawurlencode((string) $parentId).$suffix,
            ];
            $crumbs[] = [
                'label' => $ctx['child']->getLabel(),
                'url' => $base.'/'.$parent->getSlug().'/'.rawurlencode((string) $parentId).'/'.$ctx['child']->getSlug(),
            ];
        }

        return $crumbs;
    }

    private function stringId(AdminResource $resource, object $record): string
    {
        $id = $this->accessor->getValue($record, $resource->getIdentifierField());

        return \is_scalar($id) ? (string) $id : '';
    }
```

Add `use Symfony\Component\PropertyAccess\PropertyAccessorInterface;` (already added in Task 5).

- [ ] **Step 6: Create the breadcrumb partial**

`templates/admin/_breadcrumb.html.twig`:

```twig
{# Nested-resource ancestry trail (REL-17). `breadcrumbs` is a list of
   { label, url }; the last entry with a null url renders as plain text. #}
{% set breadcrumbs = breadcrumbs|default([]) %}
{% if breadcrumbs is not empty %}
    <nav aria-label="Breadcrumb" class="mb-4">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
            {% for crumb in breadcrumbs %}
                <li class="flex items-center gap-1.5">
                    {% if not loop.first %}
                        {{ atrium_icon('chevron-right', { class: 'h-3.5 w-3.5 text-gray-300 dark:text-gray-600' }) }}
                    {% endif %}
                    {% if crumb.url %}
                        <a href="{{ crumb.url }}" class="transition-colors hover:text-gray-700 dark:hover:text-gray-200">{{ crumb.label }}</a>
                    {% else %}
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ crumb.label }}</span>
                    {% endif %}
                </li>
            {% endfor %}
        </ol>
    </nav>
{% endif %}
```

> Confirm a `chevron-right` glyph ships in `assets/icons`; if not, use an existing separator glyph or a literal `/`.

- [ ] **Step 7: Include the breadcrumb in the three page templates**

At the very top of `{% block body %}` in `form_page.html.twig`, `view_page.html.twig`, and `resource.html.twig`, inside the outer container, add:

```twig
{% include '@Atrium/admin/_breadcrumb.html.twig' with { breadcrumbs: breadcrumbs|default([]) } only %}
```

(Flat pages pass no `breadcrumbs` → the partial renders nothing.)

**Also make `view_page.html.twig`'s Back link nested-aware** (it still hard-codes the flat URL `{{ panel.pathPrefix }}/{{ resource.slug }}`, which on a nested view is `/admin/task` — a 404). In `view_page.html.twig`, near the top of `{% block header_actions %}`, default the var and use it (mirroring the Task 6 change to `form_page.html.twig`):

```twig
{% set backUrl = backUrl|default(panel.pathPrefix ~ '/' ~ resource.slug) %}
```

and change the Back anchor `href="{{ panel.pathPrefix }}/{{ resource.slug }}"` to `href="{{ backUrl }}"`. `nestedView()` already passes `backUrl` = the nested index URL; flat `view()` passes none, so the default keeps flat behaviour.

- [ ] **Step 8: Write the failing breadcrumb functional test**

`tests/Functional/NestedBreadcrumbTest.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NestedBreadcrumbTest extends WebTestCase
{
    public function testBreadcrumbShowsTheParentTrail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1/task/1');

        self::assertResponseIsSuccessful();
        $nav = $crawler->filter('nav[aria-label="Breadcrumb"]');
        self::assertCount(1, $nav);
        $text = $nav->text();
        self::assertStringContainsString('Projects', $text); // parent resource label
        self::assertStringContainsString('Alpha', $text);     // parent record title (project 1 name)
        self::assertStringContainsString('Tasks', $text);     // child resource label
        // Parent record links to its view page.
        self::assertStringContainsString('/admin/project/1', $nav->html());
    }

    public function testFlatPageHasNoBreadcrumb(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('nav[aria-label="Breadcrumb"]'));
    }
}
```

> The breadcrumb parent title is the **project name** ("Alpha"): `TaskResource::parent()->recordTitle('name')` (set in Task 2) names the attribute used to title the *parent* Project record, and the resolver returns it as `recordTitleAttribute`, which `nestedBreadcrumbs()` reads off the parent record. No fixture change is needed here — `'name'` is already the value throughout.

- [ ] **Step 9: Run tests, expect pass; gates**

Run: `vendor/bin/phpunit tests/Functional/NestedBreadcrumbTest.php tests/Page/PageContextNestedTest.php` → PASS.
Run: `composer test && composer phpstan && composer cs` → green (flat pages unaffected; the corrected fixture keeps `ParentRelationResolverTest` green).

- [ ] **Step 10: Commit**

```bash
git add src/Page/PageContext.php src/Controller/AdminController.php \
        templates/admin/_breadcrumb.html.twig templates/admin/form_page.html.twig \
        templates/admin/view_page.html.twig templates/admin/resource.html.twig \
        tests/Functional/NestedBreadcrumbTest.php tests/Page/PageContextNestedTest.php \
        tests/Fixtures/Resource/TaskResource.php tests/Relation/ParentRelationResolverTest.php
git commit -m "Recursive-ready breadcrumb + PageContext ancestry/nestedUrl (REL-17)"
```

---

## Task 9: relation-manager nested mode (REL-18)

When a relation's target is a resource nested under *this* parent + relation, the manager links rows to the child's nested pages and points "New" at the nested create page, instead of opening inline modals.

**Files:**
- Modify: `src/Twig/Components/RelationManager.php`
- Modify: `templates/components/relation_manager.html.twig`
- Test: `tests/Functional/RelationManagerNestedTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Functional/RelationManagerNestedTest.php` — mount the Project→tasks relation manager (target `task` is nested under `project.tasks`) and assert nested behaviour.

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\RelationManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class RelationManagerNestedTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function manager(): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'project',
            'parentId' => '1',
            'relation' => 'tasks',
            'pathPrefix' => '/admin',
        ]);
    }

    public function testRowsLinkToNestedChildPagesAndNewLinksToNestedCreate(): void
    {
        $html = $this->manager()->render()->toString();

        // Row links and the New link carry the parent segment.
        self::assertStringContainsString('/admin/project/1/task/1', $html);     // a row → nested view
        self::assertStringContainsString('/admin/project/1/task/new', $html);   // New → nested create
        // Inline create modal trigger is gone in nested mode.
        self::assertStringNotContainsString('data-live-action-param="openCreate"', $html);
    }

    public function testOpenCreateIsANoOpInNestedMode(): void
    {
        $component = $this->manager();
        $component->call('openCreate');
        self::assertNull($component->component()->modalMode ?? null);
    }
}
```

> `RelationManager::$modalMode` is a public LiveProp; reading it via `component()` is fine (see `RelationManagerManyToManyTest::state()` for the typed-accessor idiom if PHPStan complains).

- [ ] **Step 2: Run, expect failure**

Run: `vendor/bin/phpunit tests/Functional/RelationManagerNestedTest.php` → FAIL (rowUrl null, New renders as modal trigger).

- [ ] **Step 3: Add nested detection + URL seams to `RelationManager`**

Inject `ParentRelationResolver` (append to the constructor, autowired) and add:

```php
    private ?bool $nested = null;

    /**
     * True when the target resource is nested under *this* parent + relation, so
     * rows link to the child's full nested pages and New points at nested create
     * (REL-18) instead of opening inline modals.
     */
    public function isNested(): bool
    {
        if (null !== $this->nested) {
            return $this->nested;
        }

        if (null === $this->target()->parent()) {
            return $this->nested = false;
        }

        $resolved = $this->parentResolver->resolve($this->target());

        return $this->nested = $resolved->parentResource->getSlug() === $this->parentResource()->getSlug()
            && $resolved->relation->getName() === $this->relationName;
    }
```

Add the import `use Atrium\Relation\ParentRelationResolver;` and the ctor property `private readonly ParentRelationResolver $parentResolver,`.

Override `actionContext()` for nested mode:

```php
    protected function actionContext(?string $id): ActionContext
    {
        if ($this->isNested()) {
            return new NestedActionContext(
                $this->pathPrefix,
                $this->parentResource()->getSlug(),
                $this->parentId,
                $this->target()->getSlug(),
                $id ?? '',
            );
        }

        return new ActionContext($this->pathPrefix, $this->target()->getSlug(), $id ?? '');
    }
```

Add `use Atrium\Action\NestedActionContext;`.

Replace the M4-placeholder `rowUrl()`:

```php
    protected function rowUrl(object $record, ActionContext $context): ?string
    {
        if (!$this->isNested()) {
            return null; // inline mode: row click opens the edit modal (getRowAction)
        }

        // Prefer the child's nested view page, else its nested edit page.
        if (null !== $this->target()->resolvePage('view')) {
            return $context->recordRootUrl();
        }

        return $context->recordUrl('edit');
    }
```

Make `getRowAction()` navigate (null) in nested mode:

```php
    public function getRowAction(): ?string
    {
        if ($this->isReadOnly() || $this->isManyToMany() || $this->isNested()) {
            return null; // nested rows navigate via rowUrl; M:N/read-only aren't clickable
        }

        return 'openEdit';
    }
```

Guard `openCreate()` against nested mode (the New affordance is a link, not the modal):

```php
    #[LiveAction]
    public function openCreate(): void
    {
        if ($this->isReadOnly() || $this->isManyToMany() || $this->isNested() || !$this->target()->canCreate()) {
            return;
        }

        $this->modalMode = 'create';
        $this->modalRecordId = null;
    }
```

Add a nested create URL for the template and a nested-aware New-button gate:

```php
    /** The nested create URL when the manager is nested + create is allowed, else null. */
    public function getNestedCreateUrl(): ?string
    {
        if (!$this->isNested() || $this->isReadOnly() || !$this->target()->canCreate()) {
            return null;
        }

        return rtrim($this->pathPrefix, '/').'/'.$this->parentResource()->getSlug().'/'.rawurlencode($this->parentId).'/'.$this->target()->getSlug().'/new';
    }
```

Keep `canCreateRelated()` as-is but make the inline button hide in nested mode:

```php
    public function canCreateRelated(): bool
    {
        return !$this->isReadOnly() && !$this->isManyToMany() && !$this->isNested() && $this->target()->canCreate();
    }
```

- [ ] **Step 4: Update the relation-manager template**

In `templates/components/relation_manager.html.twig`, replace the standalone New `<button>` block (lines ~16–21) so a nested manager renders a link, the inline manager keeps the modal trigger. Update the wrapping condition to also show when a nested create URL exists:

```twig
    {% if this.canCreateRelated or this.canAssociateRelated or this.canAttachRelated or this.getNestedCreateUrl %}
        <div class="flex justify-end gap-2">
            {# ... existing Attach-existing buttons unchanged ... #}
            {% if this.getNestedCreateUrl %}
                <a href="{{ this.getNestedCreateUrl }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-950">
                    {{ atrium_icon('plus', { class: 'h-4 w-4' }) }} New {{ this.singularLabel }}
                </a>
            {% elseif this.canCreateRelated %}
                <button type="button" data-action="live#action" data-live-action-param="openCreate"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-950">
                    {{ atrium_icon('plus', { class: 'h-4 w-4' }) }} New {{ this.singularLabel }}
                </button>
            {% endif %}
        </div>
    {% endif %}
```

(The create/edit/associate/attach modal blocks below stay; in nested mode none of them open because their triggers are gone and the open actions are guarded.)

- [ ] **Step 5: Run tests, expect pass; gates**

Run: `vendor/bin/phpunit tests/Functional/RelationManagerNestedTest.php` → PASS.
Run: `composer test && composer phpstan && composer cs` → green. Re-run the M2/M3 relation suites (`RelationManagerActionsTest`, `RelationManagerManyToManyTest`, `RelationManagerRenderTest`) — inline (Post→Comments) and M:N (Post↔Tags) behaviour must be unchanged because their targets declare no `parent()`.

- [ ] **Step 6: Commit**

```bash
git add src/Twig/Components/RelationManager.php templates/components/relation_manager.html.twig tests/Functional/RelationManagerNestedTest.php
git commit -m "Relation manager nested mode: row links + New link into nested pages (REL-18)"
```

---

## Task 10: docs + CHANGELOG + PRD pointer

**Files:**
- Create: `docs/integration-guide/resources/nesting.md`
- Modify: `docs/integration-guide/resources/relations.md` (cross-link), `docs/integration-guide/resources/overview.md` (parent() row + nesting link)
- Modify: `CHANGELOG.md`
- Modify: `docs/PRDs/PRD-relations-nesting.md` (mark REL-M4 status, if the file tracks milestone status)

- [ ] **Step 1: Write `docs/integration-guide/resources/nesting.md`**

Follow the canonical template (one-line summary, "When to use", a copy-pasteable PHP example showing `ProjectResource::relations()` + `TaskResource::parent()`, then an API reference). Cover:
- `AdminResource::parent(): ?ParentRelation` (default null = top-level).
- `ParentRelation::make($parentResourceClass)->relationship($name)->foreignKey($column)->recordTitle($attr)` — a method table, each with signature + one-line description; note `relationship` names the **parent's** `Relation` and `foreignKey` must equal that relation's `foreignKey` (boot-checked lazily).
- The nested URLs `/{prefix}/{parent}/{parentId}/{resource}/{...}` (index/new/{id}/{id}/edit) and that the child is scoped to both its own `scopeQuery()` and the parent FK — a cross-parent id is a 404.
- That a parent-page relation manager whose target is nested links rows into the child's nested pages and "New" into nested create (REL-18), instead of inline modals.
- The single-parent-segment limitation: each nested page carries exactly one parent segment; deeper ancestry is reached by navigating (the child becomes the parent segment one level down). The breadcrumb shows the immediate parent trail.

- [ ] **Step 2: Cross-link from `relations.md` and `overview.md`**

In `overview.md`, add to the Relations hook table a row for `parent()` (or a short "Nesting" subsection) pointing to `nesting.md`:

```markdown
| `parent()` | Declare the resource is **nested** under a parent record (`ParentRelation`); see [Nesting](nesting.md). |
```

In `relations.md`, add a "See also: [Nesting resources](nesting.md)" link near the top and a sentence that a relation whose target is nested links into nested pages rather than inline modals.

- [ ] **Step 3: Update `CHANGELOG.md`**

Under `[Unreleased]`, add a REL-M4 entry referencing REL-14..18 + REL-20: nested resources via `parent()`/`ParentRelation`, the nested route family, scoped CRUD with cross-parent 404, `NestedActionContext`, `PageContext.parentRecords`/`nestedUrl()`, the breadcrumb, and the relation-manager nested mode. **Flag the public-API surface:** new `ParentRelation` + `AdminResource::parent()` hook; `ActionContext` is no longer `final` (intentionally extensible — additive, not a break); `PageContext` gained optional constructor params (additive).

- [ ] **Step 4: Run the full gate one last time**

Run: `composer test && composer phpstan && composer cs` → all green.

- [ ] **Step 5: Commit**

```bash
git add docs/integration-guide/resources/nesting.md docs/integration-guide/resources/relations.md \
        docs/integration-guide/resources/overview.md CHANGELOG.md docs/PRDs/PRD-relations-nesting.md
git commit -m "Document nested resources (REL-14..18, REL-20)"
```

---

## Out of scope (deferred, noted not silently dropped)

- **REL-M5** — playground wiring (Course → Lessons nested on real Doctrine data), browser-verify (dark mode, no console errors), the `->using()` extraction example, final cross-linked docs, and the separate REL-22 code-review agent. M4 proves the mechanism against the array adapter + a unit/functional suite; the Doctrine end-to-end and visual verification are M5.
- **Multi-level (grandparent) URLs** — the route family carries a single parent segment by design (PRD §5.4 non-collision argument). Deeper nesting is reached by navigation (each level re-roots), not by N parent segments in one URL. `PageContext.parentRecords` is a list for forward-compat but holds the single resolved parent. Documented in `nesting.md`.
- **Doctrine relation-provider changes** — none needed: nested list scoping rides the existing `DataProviderInterface::find()` + `DataQuery.filters` (the FK condition), and nested writes ride the existing `Form` preset + `DataWriterInterface`. The `RelationDataProvider` (pivot/associate) is untouched by M4.
- **`canAttachAny($parent)` Attach-button hook** (REL-M3 review H2) — still deferred; unrelated to nesting.

## Verification (whole milestone)

- `composer test` — green, **0 risky**. New coverage: `ParentRelation` builder; `ParentRelationResolver` (valid + 4 failure modes); `NestedActionContext` 5-segment URLs; nested routing match + flat-route non-collision; nested view/edit/create/index full CRUD scoped to the parent; cross-parent + unknown-parent + undeclared-nesting 404s; parent-FK list scope + 5-segment row URLs; `PageContext.nestedUrl()` + ancestry; breadcrumb trail renders on nested pages and is absent on flat pages; relation-manager nested mode (row links + New link, `openCreate` no-op). Regression: M1/M2/M3 relation suites + flat DataTable/Form/Pages suites unchanged.
- `composer phpstan` — clean at max.
- `composer cs` — clean.
- Boot count in `KernelBootTest` updated to 30; no leaked exception handlers (Live tests `restore_exception_handler()` in `tearDown`).

## Self-review notes (author)

- **Spec coverage:** REL-14 (Task 1) · REL-15 routes+ordering (Task 4) + scoped CRUD (Tasks 5–7) · REL-16 NestedActionContext + nested tables (Tasks 3, 7, 9) · REL-17 breadcrumb + PageContext ancestry (Task 8) · REL-18 relation-manager nested links (Task 9) · REL-20 nesting validation, lazy (Task 2). REL-19/21/22 are explicitly M5.
- **Type consistency:** `ResolvedParentRelation` fields (`parentResource`, `relation`, `foreignKey`, `recordTitleAttribute`) are referenced identically in the resolver, controller, DataTable and RelationManager. `NestedActionContext(pathPrefix, parentSlug, parentId, slug, recordId)` arg order is used consistently in Tasks 3/7/9. `PageContext` new params (`parentResourceSlug`, `parentRecordId`, `parentRecords`) match between Task 8's ctor and `nestedUrl()`/controller.
- **Decision (user-approved):** validation is **lazy** (Task 2), consistent with `assertNoSlugCollisions()`/`RelationResolver`; no compiler pass.
- **Plan-review corrections folded** (see "Review corrections" at the top): explicit `AdminController` DI args [B1], `TextField`/`TextEntry` class names [B2/B3], `recordTitle('name')` consistent from Task 2 [S1], nested-aware `view_page` Back link [S2], `DataTable` ctor param position [S3], typed preset FK via `parentScalarId()` [S5].
