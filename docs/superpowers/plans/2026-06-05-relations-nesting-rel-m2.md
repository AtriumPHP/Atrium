# Relation Managers — REL-M2 (One-to-Many Actions) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the read-only one-to-many `RelationManager` (REL-M1) a full lifecycle — owned Create/Edit/Delete (+ bulk delete) scoped to the parent, and Associate/Dissociate link/unlink — with owned Create/Edit rendered as an **inline modal** on the manager (per PRD §5.3), the associate picker backed by a server-side `listLinkable`, and `canAssociate`/`canDissociate` authorization hooks.

**Architecture:** The modal Create/Edit reuses the existing `Atrium:Form` Live Component **nested inside a modal** on the `RelationManager`, rather than duplicating Form's schema-rendering/validation. `Form` gains an *embedded* mode: it pre-sets the parent foreign key on the new entity (so create links in one transaction), suppresses the page redirect, and `emitUp()`s a `relation:saved` event. The `RelationManager` listens via `#[LiveListener('relation:saved')]` to close the modal and refresh. Associate is a lighter modal — a native `Select` over `listLinkable` owned by the manager. Delete/Dissociate/BulkDelete reuse the existing confirm-modal server-action path already inherited from `AbstractRecordTable`. Authorization splits cleanly: owned actions gate on the **target** resource's `can(create|edit|delete|view)`; associate/dissociate gate on the **parent** resource's new `canAssociate`/`canDissociate($parent, $child)` hooks.

**Tech Stack:** PHP 8.4, Symfony UX Live Components (`ComponentToolsTrait::emitUp`, `#[LiveListener]`), Doctrine ORM (adapter only), PHPUnit (`InteractsWithLiveComponents`), PHPStan max, PHP-CS-Fixer Symfony ruleset.

**Gates (run after every task):** `composer test && composer phpstan && composer cs`.

---

## Review corrections (applied after plan review — read first)

A plan review against the live codebase found these; they are folded into the tasks below:

1. **Slugs are entity-derived.** `AdminResource::getSlug()` kebab-cases the *entity* short name, so `PostRelResource` (entity `Post`) is **`post`** and `CommentRelResource` (entity `Comment`) is **`comment`** — NOT `post-rel`/`comment-rel`. REL-M1's passing test already mounts `'resource' => 'post'`. Every test mount and `getModalFormProps`/Form-resource reference uses `post` / `comment`. (The manager resolves the target slug dynamically via `$this->target()->getSlug()`, so no hardcoding in production code.)
2. **`ArrayDataWriter` has no `$created`.** It exposes `public array $deleted` only. Task 7 **must** add `public array $created = [];` populated in `create()` (mandatory, not optional) for its assertion to work.
3. **`KernelBootTest:55` asserts `assertCount(22, $registry->all())`.** Any task adding a fixture resource (Task 6's `DenyDissociatePostResource`; any visible() fixture in Task 11) must bump this count to match.
4. **`Note.articleId` is already nullable** (`#[ORM\Column(name: 'article_id', nullable: true)]`) — Task 1's Doctrine dissociate test needs no fixture change.
5. **Tasks 4+5 share one green commit.** Task 4 has NO standalone commit — its parent-scope test only goes green once Task 5 adds the Delete action. Write Task 4's test, implement Task 4's `findRecord` scope and Task 5's actions, then commit once (the suite must never be committed red).
6. **Form Cancel uses a PHP-side action, not the `live#emitUp` client idiom.** Add `#[LiveAction] public function cancelForm(): void { $this->emitUp('relation:cancel'); }` to `Form` and trigger it with the repo-standard `data-action="live#action" data-live-action-param="cancelForm"` (Task 8). The `live#emitUp` data-attribute idiom is used nowhere else in the repo.
7. **Owned row View is deferred to REL-M4.** The PRD lists `View` in M2's owned-action family, but M2 uses **row-click to open the Edit modal** (Task 9's `getRowAction`), which would conflict with a competing row View affordance. Owned row navigation to a child's view/edit page is exactly REL-M4's "relation-manager row links → child nested pages". So M2 ships Create/Edit (modal) + Delete + Associate/Dissociate; owned **View** lands with nesting in M4. Documented in CHANGELOG/docs as a deliberate scope choice.
8. **PHPStan max:** annotate `getModalFormProps(): array` with `@return array<string, mixed>`; `getLinkableOptions()` with `@return array<string, string>` and guard `mixed` titles with `\is_scalar(...) ? (string) ... : $id` (already in the block).

---

## File Structure

**Data layer (no Doctrine in core — adapters only):**
- Modify `src/DataProvider/ArrayRelationProvider.php` — implement `associate`/`dissociate`/`listLinkable`/`countLinkable` (1:M).
- Modify `src/DataProvider/DoctrineRelationProvider.php` — same, via QueryBuilder + entity mutation + flush.

**Core contract (BC-relevant — flag in CHANGELOG):**
- Modify `src/Resource/AdminResource.php` — add `canAssociate(object $parent, object $child): bool` and `canDissociate(...): bool` (default-allow) + dispatch them through `can()`.

**Components:**
- Modify `src/Twig/Components/AbstractRecordTable.php` — make `actionAuthorized()` `protected` (was private) so a subclass can extend authorization; no behaviour change.
- Modify `src/Twig/Components/RelationManager.php` — owned + link/unlink actions, parent-scoped `findRecord`, modal Create/Edit state + actions + `#[LiveListener]`, associate picker state + action, parent-resource authorization override.
- Modify `src/Twig/Components/Form.php` — embedded mode (`ComponentToolsTrait`, `presetValues`, `notifyEvent`, suppress redirect, `emitUp` on save).
- Modify `src/Twig/Components/RelationManagers.php` — load the parent record once, filter relations by `->visible($parent)`.

**Templates:**
- Modify `templates/components/relation_manager.html.twig` — render the Create/Edit modal (nested `Atrium:Form`) and the Associate modal (Select) when open; wire trigger buttons.
- Modify `templates/components/form.html.twig` — embedded mode renders the field tree + a modal footer (Save/Cancel) without page chrome.
- Modify `templates/admin/view_page.html.twig` — render `Atrium:RelationManagers` with `screen: 'view'` after the entries.

**Docs:**
- Modify `docs/integration-guide/resources/relations.md` (or create if absent) — actions, associate/dissociate, authorization hooks, modal create/edit.
- Modify `CHANGELOG.md` — REL-M2 entry; flag `canAssociate`/`canDissociate` as public API additions.

**Tests:**
- `tests/DataProvider/ArrayRelationProviderTest.php`, `tests/DataProvider/DoctrineRelationProviderTest.php` — associate/dissociate/listLinkable.
- `tests/Resource/ResourceHooksTest.php` — canAssociate/canDissociate defaults + dispatch.
- `tests/Functional/RelationManagerActionsTest.php` (new) — delete, dissociate, associate, modal create/edit, parent-scope refusal, read-only-on-view, visible() filtering.
- Fixtures: extend `CommentRelResource` with `form()`; `PostRelResource` already declares the relation.

---

## Conventions used by this plan

- **Target resource** = the child resource the relation points at (e.g. `CommentRelResource`). In `RelationManager` this is `$this->target()` / `resource()`. Owned action abilities (`create`/`edit`/`delete`/`view`) gate on it.
- **Parent resource** = the resource declaring `relations()` (e.g. `PostRelResource`). In `RelationManager` this is `$this->parentResource()`. `canAssociate`/`canDissociate` gate on it, called with `($this->parent(), $child)`.
- The relation here is `PostRelResource::comments` → `oneToMany(CommentRelResource)->foreignKey('postId')->recordTitle('body')`; child `Comment(id, body, postId)`. Seed (`SampleData::relationProvider`): comments 1,2 → post 1; comment 3 → post 2.

---

## Task 1: Providers — `associate` / `dissociate` (one-to-many)

`associate` sets `child.<foreignKey> = parent.<parentIdField>`; `dissociate` clears it (sets null). The provider mutates the child and persists; the manager's surrounding `transactional()` makes the link atomic with any owned write.

**Files:**
- Modify: `src/DataProvider/ArrayRelationProvider.php`
- Modify: `src/DataProvider/DoctrineRelationProvider.php`
- Test: `tests/DataProvider/ArrayRelationProviderTest.php`, `tests/DataProvider/DoctrineRelationProviderTest.php`

- [ ] **Step 1: Write the failing array test**

In `tests/DataProvider/ArrayRelationProviderTest.php` add (mirror the existing descriptor helper that yields the `comments` 1:M descriptor with `foreignKey: 'postId'`, `parentIdField: 'id'`):

```php
public function testAssociateSetsForeignKeyAndDissociateClearsIt(): void
{
    $parent = new Post(1, 'First post');
    $orphan = new Comment(9, 'Orphan', postId: null);
    $provider = new ArrayRelationProvider([
        Comment::class => [$orphan],
    ]);

    $provider->associate($this->descriptor(), $parent, $orphan);
    self::assertSame(1, $orphan->postId);

    $provider->dissociate($this->descriptor(), $parent, $orphan);
    self::assertNull($orphan->postId);
}
```

- [ ] **Step 2: Run it — expect failure** (`LogicException: associate() is implemented in REL-M2.`)

Run: `vendor/bin/phpunit tests/DataProvider/ArrayRelationProviderTest.php --filter testAssociateSets`

- [ ] **Step 3: Implement in `ArrayRelationProvider`**

Replace the `associate`/`dissociate` stub bodies:

```php
public function associate(RelationDescriptor $relation, object $parent, object $child): void
{
    $this->assertOneToMany($relation);
    $this->accessor->setValue($child, (string) $relation->foreignKey, $this->parentId($relation, $parent));
}

public function dissociate(RelationDescriptor $relation, object $parent, object $child): void
{
    $this->assertOneToMany($relation);
    $this->accessor->setValue($child, (string) $relation->foreignKey, null);
}
```

Add two private helpers (and reuse `assertOneToMany` in `matchingChildren` in place of its inline check, keeping one source of truth):

```php
private function assertOneToMany(RelationDescriptor $relation): void
{
    if (RelationKind::OneToMany !== $relation->kind) {
        throw new \LogicException('Many-to-many link/unlink is implemented in REL-M3.');
    }
}

private function parentId(RelationDescriptor $relation, object $parent): mixed
{
    return $this->accessor->getValue($parent, $relation->parentIdField);
}
```

- [ ] **Step 4: Run the array test — expect PASS**

- [ ] **Step 5: Write the failing Doctrine test**

In `tests/DataProvider/DoctrineRelationProviderTest.php` (entities `Article`/`Note`, descriptor `foreignKey: 'articleId'`):

```php
public function testAssociateSetsForeignKeyAndDissociateClearsIt(): void
{
    $article = new Article('First');
    $this->entityManager->persist($article);
    $this->entityManager->flush();

    $note = new Note('floating', null);
    $this->entityManager->persist($note);
    $this->entityManager->flush();

    $parent = $this->entityManager->find(Article::class, $article->id);
    self::assertNotNull($parent);

    $this->provider->associate($this->descriptor(), $parent, $note);
    $this->entityManager->clear();
    self::assertSame($article->id, $this->entityManager->find(Note::class, $note->id)?->articleId);

    $reloaded = $this->entityManager->find(Note::class, $note->id);
    self::assertNotNull($reloaded);
    $this->provider->dissociate($this->descriptor(), $this->entityManager->find(Article::class, $article->id), $reloaded);
    $this->entityManager->clear();
    self::assertNull($this->entityManager->find(Note::class, $note->id)?->articleId);
}
```

> Note: `Note`'s FK must be nullable for dissociate. Confirm `tests/Fixtures/Entity/Note.php` has `#[ORM\Column(nullable: true)] public ?int $articleId`. If it is non-nullable, make it nullable in this step (it is a test fixture; record the change in the commit).

- [ ] **Step 6: Run it — expect failure** (LogicException)

- [ ] **Step 7: Implement in `DoctrineRelationProvider`**

```php
public function associate(RelationDescriptor $relation, object $parent, object $child): void
{
    $this->setForeignKey($relation, $child, $this->accessor->getValue($parent, $relation->parentIdField));
}

public function dissociate(RelationDescriptor $relation, object $parent, object $child): void
{
    $this->setForeignKey($relation, $child, null);
}

private function setForeignKey(RelationDescriptor $relation, object $child, mixed $value): void
{
    if (RelationKind::OneToMany !== $relation->kind) {
        throw new \LogicException('Many-to-many link/unlink is implemented in REL-M3.');
    }

    $this->accessor->setValue($child, (string) $relation->foreignKey, $value);
    $this->entityManager->persist($child);
    $this->entityManager->flush();
}
```

> `flush()` here enlists in the ambient `wrapInTransaction()` opened by the manager (Task 9 wraps create+associate together), so a failure rolls both back.

- [ ] **Step 8: Run both provider tests — expect PASS**

- [ ] **Step 9: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/DataProvider/ArrayRelationProvider.php src/DataProvider/DoctrineRelationProvider.php tests/DataProvider/ tests/Fixtures/Entity/Note.php
git commit -m "Implement one-to-many associate/dissociate in relation providers (REL-07, REL-10)"
```

---

## Task 2: Providers — `listLinkable` / `countLinkable` (one-to-many)

Candidates for the Associate picker: children **not already linked** to *any* parent. For 1:M a child has at most one parent, so linkable = `<foreignKey> IS NULL`, plus the target resource's `scopeQuery()` conditions (carried in `DataQuery.filters`) and pagination.

**Files:**
- Modify: `src/DataProvider/ArrayRelationProvider.php`, `src/DataProvider/DoctrineRelationProvider.php`
- Test: both provider tests

- [ ] **Step 1: Failing array test**

```php
public function testListLinkableReturnsOnlyUnlinkedChildren(): void
{
    $parent = new Post(1, 'First post');
    $provider = new ArrayRelationProvider([
        Comment::class => [
            new Comment(1, 'linked', postId: 1),
            new Comment(2, 'free a', postId: null),
            new Comment(3, 'free b', postId: null),
        ],
    ]);

    $rows = [...$provider->listLinkable($this->descriptor(), $parent, new DataQuery())];
    self::assertCount(2, $rows);
    self::assertContainsOnlyInstancesOf(Comment::class, $rows);
    self::assertSame(2, $provider->countLinkable($this->descriptor(), $parent, new DataQuery()));
}
```

- [ ] **Step 2: Run — expect failure** (LogicException)

- [ ] **Step 3: Implement in `ArrayRelationProvider`**

```php
public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
{
    $matched = $this->linkableChildren($relation, $query);

    return \array_slice($matched, max(0, $query->offset), max(1, $query->limit));
}

public function countLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): int
{
    return \count($this->linkableChildren($relation, $query));
}

/**
 * Children with no parent yet (FK is null), narrowed by the query filters (which
 * carry the target resource's scopeQuery — a relation never offers a hidden row).
 *
 * @return list<object>
 */
private function linkableChildren(RelationDescriptor $relation, DataQuery $query): array
{
    $this->assertOneToMany($relation);
    $foreignKey = (string) $relation->foreignKey;

    $children = array_values(array_filter(
        $this->records[$relation->childEntityClass] ?? [],
        fn (object $child): bool => $this->accessor->isReadable($child, $foreignKey)
            && null === $this->accessor->getValue($child, $foreignKey),
    ));

    foreach ($query->filters as $field => $value) {
        $children = array_values(array_filter(
            $children,
            fn (object $child): bool => $this->matchesFilter($child, (string) $field, $value),
        ));
    }

    return $children;
}
```

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Failing Doctrine test**

```php
public function testListLinkableReturnsOnlyUnlinkedChildren(): void
{
    $article = new Article('First');
    $this->entityManager->persist($article);
    $this->entityManager->flush();

    $this->entityManager->persist(new Note('linked', $article->id));
    $this->entityManager->persist(new Note('free', null));
    $this->entityManager->flush();
    $this->entityManager->clear();

    $parent = $this->entityManager->find(Article::class, $article->id);
    self::assertNotNull($parent);

    $rows = [...$this->provider->listLinkable($this->descriptor(), $parent, new DataQuery())];
    self::assertCount(1, $rows);
    self::assertSame('free', $rows[0]->body);
    self::assertSame(1, $this->provider->countLinkable($this->descriptor(), $parent, new DataQuery()));
}
```

- [ ] **Step 6: Run — expect failure**

- [ ] **Step 7: Implement in `DoctrineRelationProvider`**

```php
public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
{
    $qb = $this->linkableQuery($relation, $query)
        ->setFirstResult(max(0, $query->offset))
        ->setMaxResults(max(1, $query->limit));

    return array_values(array_filter(
        (array) $qb->getQuery()->getResult(),
        static fn (mixed $row): bool => \is_object($row),
    ));
}

public function countLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): int
{
    $qb = $this->linkableQuery($relation, $query)->select(\sprintf('COUNT(%s)', self::ALIAS));

    return (int) $qb->getQuery()->getSingleScalarResult();
}

private function linkableQuery(RelationDescriptor $relation, DataQuery $query): QueryBuilder
{
    if (RelationKind::OneToMany !== $relation->kind) {
        throw new \LogicException('Many-to-many linkable is implemented in REL-M3.');
    }

    $qb = $this->entityManager->getRepository($relation->childEntityClass)->createQueryBuilder(self::ALIAS);
    $qb->where($qb->expr()->isNull(self::ALIAS.'.'.$relation->foreignKey));
    $this->applyFilters($qb, $query->filters);

    return $qb;
}
```

- [ ] **Step 8: Run both — expect PASS**

- [ ] **Step 9: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/DataProvider/ tests/DataProvider/
git commit -m "Implement one-to-many listLinkable/countLinkable for the associate picker (REL-07, REL-10)"
```

---

## Task 3: `AdminResource` — `canAssociate` / `canDissociate` hooks (BC-relevant)

Public API additions. Default-allow; dispatched through `can()` so the manager can gate uniformly. **These need two records** (`$parent`, `$child`), so they are NOT folded into `can(string, ?record)` — the manager calls them directly (Task 6/10). `can()` still gains the ability names for symmetry and so an app overriding `can()` sees them.

**Files:**
- Modify: `src/Resource/AdminResource.php`
- Test: `tests/Resource/ResourceHooksTest.php`

- [ ] **Step 1: Failing test**

```php
public function testRelationLinkAbilitiesDefaultAllowAndAreOverridable(): void
{
    $resource = new class extends AdminResource {
        public function getEntityClass(): string { return \stdClass::class; }
        public function canDissociate(object $parent, object $child): bool { return false; }
    };

    $parent = new \stdClass();
    $child = new \stdClass();

    self::assertTrue($resource->canAssociate($parent, $child));
    self::assertFalse($resource->canDissociate($parent, $child));
}
```

- [ ] **Step 2: Run — expect failure** (undefined method `canAssociate`)

- [ ] **Step 3: Implement in `AdminResource`** (place next to `canDelete`/`canView`)

```php
/**
 * Whether $child may be linked to $parent through a one-to-many relation
 * (set its foreign key). Default-allow; override to restrict. Re-checked at
 * execution by the relation manager. Public API (REL-12).
 */
public function canAssociate(object $parent, object $child): bool
{
    return true;
}

/**
 * Whether $child may be unlinked from $parent (clear its foreign key; the
 * record persists). Default-allow; override to restrict. Public API (REL-12).
 */
public function canDissociate(object $parent, object $child): bool
{
    return true;
}
```

These take two subjects, so they are not part of the single-subject `can()` dispatch; the relation manager calls them directly. (Documented in the `can()` docblock — add a line: "Relation link/unlink use `canAssociate`/`canDissociate`, which take both parent and child.")

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Gates + CHANGELOG flag + commit**

Add to `CHANGELOG.md` `[Unreleased]` under a "Public API" note: `AdminResource::canAssociate(object $parent, object $child)` and `canDissociate(...)` added (default-allow; override to restrict relation link/unlink).

```bash
composer test && composer phpstan && composer cs
git add src/Resource/AdminResource.php tests/Resource/ResourceHooksTest.php CHANGELOG.md
git commit -m "Add canAssociate/canDissociate authorization hooks (REL-12, public API)"
```

---

## Task 4: `AbstractRecordTable` — open the authorization seam + parent-scoped `findRecord`

Make `actionAuthorized()` `protected` so `RelationManager` can extend it for the `associate`/`dissociate` abilities (which gate on the *parent* resource). Pure refactor — the existing `DataTable`/table action tests are the regression guard. Then scope `RelationManager::findRecord()` by the parent FK so a forged cross-parent id is refused.

**Files:**
- Modify: `src/Twig/Components/AbstractRecordTable.php`
- Modify: `src/Twig/Components/RelationManager.php`
- Test: `tests/Functional/RelationManagerActionsTest.php` (new)

- [ ] **Step 1: Make `actionAuthorized` protected**

In `AbstractRecordTable.php` change `private function actionAuthorized(Action $action, ?object $record): bool` to `protected function actionAuthorized(...)`. No body change.

- [ ] **Step 2: Run the full suite — expect PASS** (no behaviour change)

Run: `composer test`

- [ ] **Step 3: Failing parent-scope test** (new file `tests/Functional/RelationManagerActionsTest.php`)

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\DataProvider\ArrayRelationProvider;
use Atrium\Twig\Components\RelationManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class RelationManagerActionsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    private function manager(string $parentId = '1'): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'post',
            'parentId' => $parentId,
            'relation' => 'comments',
            'pathPrefix' => '/admin',
        ]);
    }

    public function testDeleteRefusesAChildOfAnotherParent(): void
    {
        // Comment 3 belongs to post 2; the manager is mounted for post 1.
        $component = $this->manager('1');
        $component->call('requestAction', ['name' => 'delete', 'id' => '3']);

        // Not confirmable: findRecord is parent-scoped, so the cross-parent id resolves to null.
        self::assertNull($component->component()->confirmingAction);
        self::assertCount(0, $this->writer()->deleted);
    }

    private function writer(): ArrayDataWriter
    {
        return self::getContainer()->get(ArrayDataWriter::class);
    }

    private function relationProvider(): ArrayRelationProvider
    {
        return self::getContainer()->get(ArrayRelationProvider::class);
    }
}
```

> The slug for `PostRelResource` is **`post`** (kebab of the entity `Post`), and `CommentRelResource` is `comment`. The relation manager's row actions (delete) land in Task 5 — this test currently fails because `requestAction('delete')` finds no `delete` action AND because findRecord is not yet scoped. It passes once Task 5 adds the Delete action and this task scopes findRecord. Per "Review corrections" #5, Tasks 4+5 share one green commit — do NOT commit a red suite after Task 4.

- [ ] **Step 4: Scope `RelationManager::findRecord` by the parent FK**

Replace the body:

```php
protected function findRecord(string $id): ?object
{
    // Parent-scoped: a child of another parent (forged id) must resolve to null,
    // so a mutating row action can never reach across parents.
    $filters = $this->target()->scopeFilters();
    $filters[(string) $this->descriptor()->foreignKey] = $this->accessor->getValue(
        $this->parent(),
        $this->descriptor()->parentIdField,
    );

    return $this->dataProvider->find(
        $this->entityClass(),
        $id,
        $filters,
        $this->descriptor()->childIdField,
    );
}
```

> `DataProviderInterface::find($class, $id, $filters, $idField)` already AND-composes `$filters` (the array adapter and Doctrine adapter both apply them). The added FK filter is the parent-scope clause.

- [ ] **Step 5: Run after Task 5** — the test passes once Delete exists.

- [ ] **Step 6: Commit** (with Task 5, since the test needs the Delete action)

---

## Task 5: `RelationManager` — owned Delete + Bulk Delete

Reuse `DeleteAction` (row) and `BulkDeleteAction`. The inherited `AbstractRecordTable::executeAction`/`runBulkAction` already run them through the confirm-modal server-action path and the `transactional()` wrapper; authorization flows through `actionAuthorized` → `resource()->can('delete', $child)` (the **target** resource). `findRecord` is now parent-scoped (Task 4), so bulk and row delete only ever touch this parent's children.

**Files:**
- Modify: `src/Twig/Components/RelationManager.php`
- Modify: `tests/Fixtures/Resource/CommentRelResource.php` (add a `form()` for later modal tasks; harmless now)
- Test: `tests/Functional/RelationManagerActionsTest.php`

- [ ] **Step 1: Failing test** (append)

```php
public function testDeletesAChildOfThisParent(): void
{
    $component = $this->manager('1');
    $component->call('requestAction', ['name' => 'delete', 'id' => '1']);
    self::assertSame('delete', $component->component()->confirmingAction);

    $component->call('confirmAction');
    self::assertCount(1, $this->writer()->deleted);
}
```

- [ ] **Step 2: Run — expect failure** (no `delete` action yet)

- [ ] **Step 3: Implement record + bulk actions**

In `RelationManager`, add imports `use Atrium\Table\Action\DeleteAction; use Atrium\Table\Action\BulkDeleteAction;` and replace the stub returns:

```php
public function getRecordActions(): array
{
    if ($this->isReadOnly()) {
        return [];
    }

    return [
        DeleteAction::make(),
    ];
}

public function getBulkActions(): array
{
    if ($this->isReadOnly()) {
        return [];
    }

    return [
        BulkDeleteAction::make(),
    ];
}
```

> `getRecordActions()` and `getBulkActions()` are defined on `AbstractRecordTable` reading `tableConfig()`; override them here so the relation manager owns its action set. (Edit/View row actions are added in Task 9. Dissociate in Task 6.)

Add the `isReadOnly()` helper (used throughout M2; full logic lands in Task 12):

```php
/**
 * A relation manager is read-only on the View screen when the relation opts in
 * (readOnlyOnView, default true) — all mutators hidden, leaving list + pagination.
 */
private function isReadOnly(): bool
{
    return 'view' === $this->screen && $this->relation()->isReadOnlyOnView();
}
```

> Confirm `Relation::isReadOnlyOnView(): bool` exists (REL-M1 added `readOnlyOnView()`); if the getter is named differently (e.g. `getReadOnlyOnView`), use that.

- [ ] **Step 4: Run delete + parent-scope tests (Task 4 Step 3) — expect PASS**

- [ ] **Step 5: Gates + commit** (covers Task 4 + Task 5)

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/AbstractRecordTable.php src/Twig/Components/RelationManager.php tests/Functional/RelationManagerActionsTest.php
git commit -m "Owned delete + bulk delete on the relation manager, parent-scoped (REL-05)"
```

---

## Task 6: `RelationManager` — Dissociate row action + parent-resource authorization

`Dissociate` clears the child's FK via the relation provider (the record persists). Its handler captures the descriptor + parent and calls `relationProvider->dissociate`. Authorization gates on the **parent** resource's `canDissociate($parent, $child)` — so `RelationManager` overrides `actionAuthorized` to route the `dissociate` (and future `associate`) ability to the parent resource.

**Files:**
- Modify: `src/Twig/Components/RelationManager.php`
- Test: `tests/Functional/RelationManagerActionsTest.php`

- [ ] **Step 1: Failing test**

```php
public function testDissociateClearsTheChildForeignKey(): void
{
    $component = $this->manager('1');
    $component->call('requestAction', ['name' => 'dissociate', 'id' => '1']);
    self::assertSame('dissociate', $component->component()->confirmingAction);

    $component->call('confirmAction');

    // Comment 1's postId was 1; dissociate sets it to null. It is no longer related to post 1.
    $remaining = [...$this->relationProvider()->listRelated(
        // resolve the descriptor the same way the manager does, or assert via the seeded object:
        $this->descriptorForComments(),
        new \Atrium\Tests\Fixtures\Entity\Post(1, 'First post'),
        new \Atrium\DataProvider\DataQuery(),
    )];
    self::assertNotContains('1', array_map(static fn ($c) => (string) $c->id, $remaining));
}
```

Add a small helper to the test building the same descriptor (or fetch comment 1 from the provider's seed and assert `postId` is null — simpler):

```php
private function descriptorForComments(): \Atrium\Relation\RelationDescriptor
{
    return (new \Atrium\Relation\RelationResolver(
        self::getContainer()->get(\Atrium\Resource\ResourceRegistry::class),
    ))->resolve(
        (new \Atrium\Tests\Fixtures\Resource\PostRelResource())->relations()[0],
        'id',
    );
}
```

- [ ] **Step 2: Run — expect failure** (no `dissociate` action)

- [ ] **Step 3: Add the Dissociate action**

Extend `getRecordActions()`:

```php
public function getRecordActions(): array
{
    if ($this->isReadOnly()) {
        return [];
    }

    return [
        $this->dissociateAction(),
        DeleteAction::make(),
    ];
}

private function dissociateAction(): Action
{
    $descriptor = $this->descriptor();
    $parent = $this->parent();

    return Action::make('dissociate')
        ->label('Detach')
        ->icon('unlink')
        ->color('gray')
        ->authorize('dissociate')
        ->requiresConfirmation()
        ->confirmationMessage('Detach this record? It will be unlinked but not deleted.')
        ->action(function (object $child) use ($descriptor, $parent): void {
            $this->relationProvider->dissociate($descriptor, $parent, $child);
        });
}
```

> The handler signature `(object $child)` is fine — `executeAction` calls `$handler($record, $this->writer)` and the extra `$writer` arg is ignored. The call runs inside the inherited `transactional()` wrapper.

- [ ] **Step 4: Override authorization to gate on the parent resource**

Add to `RelationManager`:

```php
/**
 * Owned abilities (create/edit/delete/view) gate on the target resource via the
 * base; relation link/unlink gate on the *parent* resource's canAssociate/
 * canDissociate, which take both the parent record and the child (REL-12).
 */
protected function actionAuthorized(Action $action, ?object $record): bool
{
    return match ($action->getAbility()) {
        'associate' => null !== $record && $this->parentResource()->canAssociate($this->parent(), $record),
        'dissociate' => null !== $record && $this->parentResource()->canDissociate($this->parent(), $record),
        default => parent::actionAuthorized($action, $record),
    };
}
```

- [ ] **Step 5: Run — expect PASS.** Add a denial test:

```php
public function testDissociateHiddenAndRefusedWhenParentDenies(): void
{
    // Register a parent resource whose canDissociate() returns false (fixture),
    // mount its manager, assert the row has no 'dissociate' action and that a
    // forced requestAction does not confirm.
}
```

> Add a fixture `DenyDissociatePostResource` (copy of `PostRelResource` with `public function canDissociate(object $parent, object $child): bool { return false; }`) and register it in `AtriumTestKernel`. Assert `$component->render()` contains no "Detach" and `requestAction('dissociate', '1')` leaves `confirmingAction` null.

- [ ] **Step 6: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/RelationManager.php tests/Functional/RelationManagerActionsTest.php tests/Fixtures/Resource/ tests/Functional/AtriumTestKernel.php
git commit -m "Dissociate row action with parent-resource authorization (REL-06, REL-12)"
```

---

## Task 7: `Form` — embedded mode (preset FK, suppress redirect, emit on save)

`Form` learns to run *inside a modal*: pre-set values on the new entity (the parent FK), no page redirect, and on a successful save `emitUp()` a configurable event so the hosting `RelationManager` can close + refresh. Backward compatible — all new mount params default off.

**Files:**
- Modify: `src/Twig/Components/Form.php`
- Test: `tests/Functional/FormComponentTest.php` (or a new `FormEmbeddedTest.php`)

- [ ] **Step 1: Failing test** (`tests/Functional/FormEmbeddedTest.php`)

```php
public function testEmbeddedCreateAppliesPresetForeignKeyAndEmits(): void
{
    $component = $this->createLiveComponent('Atrium:Form', [
        'resource' => 'comment',
        'embedded' => true,
        'presetValues' => ['postId' => '1'],
        'notifyEvent' => 'relation:saved',
    ]);

    $component->set('formData', ['body' => 'A new comment']);
    $component->call('save');

    // The created Comment carries the preset FK.
    $created = $this->writer()->created;     // ArrayDataWriter records created entities
    self::assertCount(1, $created);
    self::assertSame(1, $created[0]->postId);
    self::assertSame('A new comment', $created[0]->body);
}
```

> If `ArrayDataWriter` does not expose `created`, add a public `array $created = []` populated in `create()` (mirror the existing `deleted`). Record that fixture change in the commit.

- [ ] **Step 2: Run — expect failure** (unknown mount keys / FK not applied)

- [ ] **Step 3: Implement embedded mode**

Add the trait + props to `Form`:

```php
use Symfony\UX\LiveComponent\ComponentToolsTrait;
// ...
class Form
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use InteractsWithActions;

    #[LiveProp]
    public bool $embedded = false;

    /** Values force-applied to the entity on save (e.g. a relation's parent FK). @var array<string, scalar|null> */
    #[LiveProp]
    public array $presetValues = [];

    /** Event emitted to the host after a successful embedded save. */
    #[LiveProp]
    public ?string $notifyEvent = null;
```

Extend `mount()`:

```php
public function mount(string $resource, ?string $entityId = null, ?string $redirectAfterSave = null, string $pathPrefix = '', bool $embedded = false, array $presetValues = [], ?string $notifyEvent = null): void
{
    $this->resource = $resource;
    $this->entityId = $entityId;
    $this->redirectAfterSave = $redirectAfterSave;
    $this->pathPrefix = $pathPrefix;
    $this->embedded = $embedded;
    $this->presetValues = $presetValues;
    $this->notifyEvent = $notifyEvent;
    $this->formData = $this->initialFormData();
    $this->previousFormData = $this->formData;
}
```

In `save()`, after the field-writing loop and before the `transactional()` call, apply the preset values to the entity (trusted developer-supplied keys, parameter-bound):

```php
foreach ($this->presetValues as $name => $value) {
    if ($this->accessor->isWritable($entity, $name)) {
        $this->accessor->setValue($entity, $name, $value);
    }
}
```

At the end of `save()`, replace the redirect tail:

```php
$this->saved = true;

if ($this->embedded) {
    if (null !== $this->notifyEvent) {
        $this->emitUp($this->notifyEvent, ['id' => $this->entityId]);
    }

    return null; // embedded forms never navigate
}

if (null !== $this->redirectAfterSave) {
    return new RedirectResponse($this->redirectAfterSave);
}

return null;
```

> `presetValues` are strings off the wire (e.g. `'1'`); since they are applied via `PropertyAccessor::setValue`, a typed property (`?int $postId`) coerces. If a target uses a non-coercible type, the integrator can pass the typed value through the relation form hook — out of scope here.

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/Form.php src/DataProvider/ArrayDataWriter.php tests/Functional/FormEmbeddedTest.php
git commit -m "Form: embedded mode with preset FK + saved event for modal hosting (REL-05)"
```

---

## Task 8: `Form` template — embedded (chrome-less) rendering

In embedded mode the form renders only its field tree plus a modal footer (Save / Cancel), no page heading/header-actions/saved-banner. The modal shell itself is provided by the `RelationManager` (Task 9); the Cancel button emits a host-handled action.

**Files:**
- Modify: `templates/components/form.html.twig`
- Test: covered by Task 9's render assertions

- [ ] **Step 1:** Read `templates/components/form.html.twig` and identify the page-chrome blocks (heading, header actions, saved banner) vs. the `<form>` field tree.

- [ ] **Step 2:** Wrap chrome in `{% if not this.embedded %}…{% endif %}`, so embedded renders the field tree + submit only. Add an embedded footer:

```twig
{% if this.embedded %}
    <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="atrium-btn atrium-btn-secondary"
                data-action="live#emitUp" data-live-event-param="relation:cancel">Cancel</button>
        <button type="button" class="atrium-btn atrium-btn-primary"
                data-action="live#action" data-live-action-param="save">Save</button>
    </div>
{% endif %}
```

> Match the project's existing button classes/markup (copy from the non-embedded footer). The Cancel path: emit `relation:cancel` up to the `RelationManager`, which closes the modal (Task 9 adds the listener). If `live#emitUp` is not the project's idiom, use a host-bound action instead — Task 9 can expose a `cancelModal` reachable via a parent action; pick whichever the codebase already uses for child→parent signalling (the emit/listener pair is the verified mechanism).

- [ ] **Step 3:** Render the bundle's existing functional form tests — expect PASS (non-embedded path unchanged).

- [ ] **Step 4:** Commit with Task 9 (the template is only exercised once the modal hosts it).

---

## Task 9: `RelationManager` — modal Create/Edit (nested Form) + header New / row Edit

The manager holds modal open/close state, renders the nested `Atrium:Form` in a modal when open, and listens for `relation:saved` (close + refresh) and `relation:cancel` (close). The header "New" and row "Edit" actions open the modal instead of navigating.

**Files:**
- Modify: `src/Twig/Components/RelationManager.php`
- Modify: `templates/components/relation_manager.html.twig`
- Test: `tests/Functional/RelationManagerActionsTest.php`

- [ ] **Step 1: Failing test**

```php
public function testOpenCreateShowsAModalFormAndSaveRefreshes(): void
{
    $component = $this->manager('1');

    $component->call('openCreate');
    self::assertSame('create', $component->component()->modalMode);
    self::assertStringContainsString('Atrium:Form', $component->render()->toString());

    // The nested Form emits relation:saved on save; the manager closes the modal.
    $component->emit('relation:saved', ['id' => '99']);
    self::assertNull($component->component()->modalMode);
}
```

> `TestLiveComponent::emit($event, $args)` drives the listener directly. Assert the modal closes; the nested Form's own save is covered by Task 7.

- [ ] **Step 2: Run — expect failure** (no `openCreate`)

- [ ] **Step 3: Add modal state + actions + listeners**

```php
use Symfony\UX\LiveComponent\Attribute\LiveListener;
// ...

/** 'create' | 'edit' | 'associate' | null (closed). */
#[LiveProp]
public ?string $modalMode = null;

/** The id being edited (null for create). */
#[LiveProp]
public ?string $modalRecordId = null;

#[LiveAction]
public function openCreate(): void
{
    if ($this->isReadOnly() || !$this->target()->canCreate()) {
        return;
    }
    $this->modalMode = 'create';
    $this->modalRecordId = null;
}

#[LiveAction]
public function openEdit(#[LiveArg] string $id): void
{
    $record = $this->findRecord($id);   // parent-scoped
    if ($this->isReadOnly() || null === $record || !$this->target()->canEdit($record)) {
        return;
    }
    $this->modalMode = 'edit';
    $this->modalRecordId = $id;
}

#[LiveAction]
public function closeModal(): void
{
    $this->modalMode = null;
    $this->modalRecordId = null;
}

#[LiveListener('relation:saved')]
public function onRelationSaved(): void
{
    $this->closeModal();
    // Drop cached counts/pages so the table reflects the new/edited child.
    $this->refreshRecords();
}

#[LiveListener('relation:cancel')]
public function onRelationCancel(): void
{
    $this->closeModal();
}
```

Add a `refreshRecords()` protected helper to `AbstractRecordTable` (the cache-reset block already used in `executeAction` — extract it so both call it):

```php
protected function refreshRecords(): void
{
    $this->totalCount = null;
    $this->pageIds = null;
    $this->pageRecords = null;
    $this->page = min($this->page, $this->getPageCount());
}
```

> Replace the inline cache-reset in `executeAction`/`runBulkAction` with `$this->refreshRecords();` (small DRY refactor; the table tests guard it).

Expose the data the template needs to mount the nested Form:

```php
public function getModalFormProps(): array
{
    return [
        'resource' => $this->target()->getSlug(),
        'entityId' => $this->modalRecordId,
        'embedded' => true,
        'presetValues' => ['create' === $this->modalMode ? (string) $this->descriptor()->foreignKey : '__noop__' => $this->parentIdValue()],
        'notifyEvent' => 'relation:saved',
        'pathPrefix' => $this->pathPrefix,
    ];
}

private function parentIdValue(): string
{
    $value = $this->accessor->getValue($this->parent(), $this->descriptor()->parentIdField);

    return \is_scalar($value) ? (string) $value : '';
}
```

> For edit, the FK is already set on the record; only create needs the preset. Simpler/clearer: compute `presetValues` as `'create' === $this->modalMode ? [foreignKey => parentId] : []`. Use that form:

```php
public function getModalFormProps(): array
{
    $preset = 'create' === $this->modalMode
        ? [(string) $this->descriptor()->foreignKey => $this->parentIdValue()]
        : [];

    return [
        'resource' => $this->target()->getSlug(),
        'entityId' => $this->modalRecordId,
        'embedded' => true,
        'presetValues' => $preset,
        'notifyEvent' => 'relation:saved',
        'pathPrefix' => $this->pathPrefix,
    ];
}

public function isModalOpen(): bool
{
    return null !== $this->modalMode;
}
```

- [ ] **Step 4: Wire header New + row Edit actions**

Add a header `CreateAction`-like action that opens the modal (a plain server action, not the navigating `CreateAction`):

```php
public function getHeaderActions(): array
{
    if ($this->isReadOnly()) {
        return [];
    }

    $actions = [];
    if ($this->target()->canCreate()) {
        $actions[] = Action::make('createRelated')
            ->label('New '.$this->target()->getSingularLabel())
            ->icon('plus')
            ->color('primary')
            ->button()
            ->action(fn () => null); // server action; the trigger calls openCreate (see template)
    }
    // Associate header action is added in Task 10.

    return $actions;
}
```

> Simpler than threading through the action view system: render the header "New" button directly in the template as a `data-live-action-param="openCreate"` trigger, and the row "Edit" as `data-live-action-param="openEdit"` with the id. Prefer that — drop `createRelated` from `getHeaderActions()` and render explicit buttons in the template (Step 5), keeping the action subsystem for confirm-driven Delete/Dissociate only. Keep `getHeaderActions()` returning `[]` for create (template renders New) but **return the Associate action there in Task 10** so its picker integrates with standalone views — or likewise render Associate as an explicit trigger. Choose explicit template triggers for open-modal affordances (no confirm semantics needed); reserve `Action` for Delete/Dissociate/Associate-submit.

  **Decision for this plan:** render New/Edit as explicit template buttons calling `openCreate`/`openEdit`; keep `getHeaderActions()`/`getRecordActions()` for the confirm-driven Delete/Dissociate (Tasks 5–6) only. This avoids inventing a "server action with no handler".

So `getHeaderActions()` stays `[]` (until Associate in Task 10), and the row actions remain `[dissociate, delete]`. Edit/New are template triggers.

- [ ] **Step 5: Modal + triggers in `relation_manager.html.twig`**

Read the current template (it includes `_record_table.html.twig`). Add, above the table partial, a header "New" button when `not this.isReadOnly` and `this.target.canCreate` (expose a `canCreateRelated()` bool helper to avoid calling resource methods in Twig):

```twig
{% if this.canCreateRelated %}
    <div class="mb-3 flex justify-end">
        <button type="button" class="atrium-btn atrium-btn-primary"
                data-action="live#action" data-live-action-param="openCreate">
            {{ atrium_icon('plus', { class: 'h-4 w-4' }) }} New {{ this.singularLabel }}
        </button>
    </div>
{% endif %}
```

Add a row Edit trigger: the simplest path is to render Edit as a row `Action` with a `url(null)` server action — but per Step 4 we use explicit triggers. Since `_record_table.html.twig` renders row actions from `getRows()[].actions`, add an "edit" entry there is awkward. **Instead**, add Edit as a real row `Action` whose handler opens the modal is not possible (handlers mutate records, not component state). So render Edit via a dedicated row-action column the relation template adds, OR make Edit a `url`-style action that points at a `live#action`. 

**Resolution:** add an `editRelated` row affordance by giving `RelationManager` a row-action that the shared partial already supports as a *link*, but pointed at a Live action is not a URL. Cleanest within the existing partial: keep Edit out of `getRows()` and instead render an extra Edit button per row in a relation-specific wrapper. Since `_record_table.html.twig` is shared, add an optional template block: render the manager's own table via the partial, then rely on **row click** to open edit (Task wires `rowUrl`/row click to `openEdit`). 

Given the shared-partial constraint, implement row Edit as: override `rowUrl()` to return null (no navigation) and add Edit to `getRecordActions()` as an `Action` with a handler that is intercepted — not feasible. **Final approach:** introduce a minimal seam in `_record_table.html.twig`: it already loops row actions; allow a row action to be a "live action" by letting `Action::action()` carry an `openModal` marker is over-engineering.

**Adopt the pragmatic, low-risk choice:** for REL-M2, render row **Edit** as an explicit button injected by the relation template by iterating `this.getRows` itself is internal. Simplest correct option: add a thin `editTrigger` to each row view. Implement by overriding `getRows()` in `RelationManager` to append an `edit` flag, and extend the shared partial with: `{% if row.editUrl is defined %}…{% endif %}` is again partial coupling.

> **Plan note (resolve at implementation):** The owned-Edit affordance has two viable wirings against the existing shared partial: (a) **row click opens edit** — set a per-row data attribute (`data-live-action-param="openEdit"`, `data-live-id-param="row.id"`) on the `<tr>` in `_record_table.html.twig`, guarded by a `this.rowOpensEdit` flag the manager sets; (b) add a single optional "edit" row-action slot to the partial. Prefer (a): it reuses the existing clickable-row mechanism (VIEW-M4b already made rows clickable via `rowUrl`), swapping the URL navigation for a `live#action` when the host returns a "row action name" instead of a URL. Implement by having `AbstractRecordTable` expose `rowAction(): ?string` (default null; `DataTable` keeps null so it uses `rowUrl`; `RelationManager` returns `'openEdit'` when not read-only). The partial: if `this.rowAction` is set, render the row as a `live#action` trigger with the id; else use `row.url`. This is a small, regression-guarded change to the shared partial and keeps both hosts working.

Implement seam (a):
- `AbstractRecordTable`: `public function getRowAction(): ?string { return null; }`
- `RelationManager`: `public function getRowAction(): ?string { return $this->isReadOnly() ? null : 'openEdit'; }`
- `_record_table.html.twig`: on the row element, `{% if this.getRowAction %} data-action="live#action" data-live-action-param="{{ this.getRowAction }}" data-live-id-param="{{ row.id }}" class="cursor-pointer" {% elseif row.url %} … existing … {% endif %}`

Add the Create/Edit modal shell (rendered when `this.isModalOpen`), hosting the nested Form:

```twig
{% if this.isModalOpen and this.modalMode in ['create', 'edit'] %}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-black/40"
                data-action="live#action" data-live-action-param="closeModal" aria-label="Close"></button>
        <div role="dialog" aria-modal="true"
             class="relative z-10 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold">
                {{ this.modalMode == 'create' ? 'New ' : 'Edit ' }}{{ this.singularLabel }}
            </h2>
            {{ component('Atrium:Form', this.getModalFormProps) }}
        </div>
    </div>
{% endif %}
```

Add the Twig-facing helpers to `RelationManager`:

```php
public function canCreateRelated(): bool
{
    return !$this->isReadOnly() && $this->target()->canCreate();
}

public function getSingularLabel(): string
{
    return $this->target()->getSingularLabel();
}
```

(Expose `modalMode`/`isModalOpen`/`getModalFormProps`/`getRowAction` already public.)

- [ ] **Step 6: Run — expect PASS** (`openCreate` shows the modal; `relation:saved` closes it)

- [ ] **Step 7: Gates + commit** (covers Tasks 8 + 9)

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/RelationManager.php src/Twig/Components/AbstractRecordTable.php templates/components/relation_manager.html.twig templates/components/form.html.twig templates/components/_record_table.html.twig tests/Functional/RelationManagerActionsTest.php
git commit -m "Inline modal create/edit on the relation manager via nested Form (REL-05)"
```

---

## Task 10: `RelationManager` — Associate picker (modal over `listLinkable`)

A header "Attach existing" affordance opens a lighter modal: a native `Select` populated from `listLinkable` (titled by `recordTitle`). Submitting calls `relationProvider->associate` inside a transaction, gated by the parent resource's `canAssociate`.

**Files:**
- Modify: `src/Twig/Components/RelationManager.php`
- Modify: `templates/components/relation_manager.html.twig`
- Test: `tests/Functional/RelationManagerActionsTest.php`

- [ ] **Step 1: Failing test**

```php
public function testAssociateLinksAnExistingFreeRecord(): void
{
    // Seed a free comment (postId null) id 9 in the relation provider fixture.
    $component = $this->manager('1');
    $component->call('openAssociate');
    self::assertSame('associate', $component->component()->modalMode);

    $component->set('associateId', '9');
    $component->call('submitAssociate');

    self::assertNull($component->component()->modalMode);          // closed on success
    // Comment 9 now belongs to post 1:
    $rows = [...$this->relationProvider()->listRelated($this->descriptorForComments(), new Post(1, 'First post'), new DataQuery())];
    self::assertContains('9', array_map(static fn ($c) => (string) $c->id, $rows));
}
```

> Add `new Comment(9, 'Free', postId: null)` to `SampleData::relationProvider()`.

- [ ] **Step 2: Run — expect failure**

- [ ] **Step 3: Implement the picker**

```php
#[LiveProp(writable: true)]
public string $associateId = '';

#[LiveAction]
public function openAssociate(): void
{
    if ($this->isReadOnly()) {
        return;
    }
    $this->modalMode = 'associate';
    $this->associateId = '';
}

#[LiveAction]
public function submitAssociate(): void
{
    if ($this->isReadOnly() || '' === $this->associateId) {
        return;
    }

    $child = $this->findLinkable($this->associateId);
    if (null === $child || !$this->parentResource()->canAssociate($this->parent(), $child)) {
        return;
    }

    $this->writer->transactional(function () use ($child): void {
        $this->relationProvider->associate($this->descriptor(), $this->parent(), $child);
    });

    $this->closeModal();
    $this->associateId = '';
    $this->refreshRecords();
}

/**
 * Render-ready options for the picker: linkable records titled by recordTitle,
 * keyed by child id. Excludes already-linked (listLinkable) and respects the
 * target's scopeQuery via the relation descriptor.
 *
 * @return array<string, string>
 */
public function getLinkableOptions(): array
{
    $options = [];
    $query = new DataQuery(offset: 0, limit: 100);
    foreach ($this->relationProvider->listLinkable($this->descriptor(), $this->parent(), $query) as $record) {
        $id = $this->recordId($record);
        if (null === $id) {
            continue;
        }
        $title = $this->accessor->getValue($record, $this->descriptor()->recordTitleAttribute);
        $options[$id] = \is_scalar($title) ? (string) $title : $id;
    }

    return $options;
}

public function canAssociateRelated(): bool
{
    return !$this->isReadOnly();
}

private function findLinkable(string $id): ?object
{
    foreach ($this->relationProvider->listLinkable($this->descriptor(), $this->parent(), new DataQuery(offset: 0, limit: 1000)) as $record) {
        if ($this->recordId($record) === $id) {
            return $record;
        }
    }

    return null;
}
```

> `findLinkable` re-checks the candidate is genuinely linkable (server-side), so a forged `associateId` outside `listLinkable` (already-linked or out-of-scope) is refused.

- [ ] **Step 4: Picker modal + trigger in the template**

Add the header trigger (next to New):

```twig
{% if this.canAssociateRelated %}
    <button type="button" class="atrium-btn atrium-btn-secondary"
            data-action="live#action" data-live-action-param="openAssociate">
        {{ atrium_icon('link', { class: 'h-4 w-4' }) }} Attach existing
    </button>
{% endif %}
```

Add the picker modal:

```twig
{% if this.isModalOpen and this.modalMode == 'associate' %}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-black/40"
                data-action="live#action" data-live-action-param="closeModal" aria-label="Close"></button>
        <div role="dialog" aria-modal="true"
             class="relative z-10 w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold">Attach existing {{ this.singularLabel }}</h2>
            <select data-model="associateId" class="atrium-input w-full">
                <option value="">—</option>
                {% for value, label in this.getLinkableOptions %}
                    <option value="{{ value }}">{{ label }}</option>
                {% endfor %}
            </select>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="atrium-btn atrium-btn-secondary"
                        data-action="live#action" data-live-action-param="closeModal">Cancel</button>
                <button type="button" class="atrium-btn atrium-btn-primary"
                        data-action="live#action" data-live-action-param="submitAssociate">Attach</button>
            </div>
        </div>
    </div>
{% endif %}
```

> Match the project's actual input/button classes (copy from `form.html.twig`/`confirm_modal.html.twig`).

- [ ] **Step 5: Run — expect PASS.** Add: empty-`associateId` submit is a no-op; out-of-scope id refused.

- [ ] **Step 6: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/RelationManager.php templates/components/relation_manager.html.twig tests/Functional/RelationManagerActionsTest.php tests/Fixtures/Data/SampleData.php
git commit -m "Associate picker over listLinkable on the relation manager (REL-07, REL-12)"
```

---

## Task 11: `RelationManagers` host — `visible(fn($parent))` filtering

The host loads the parent record once and drops relations whose `->visible($parent)` predicate is false (REL-M1 added `Relation::visible()`/`isVisibleFor($parent)`). Avoids N parent loads (each manager already loads it lazily, but the host needs it to decide visibility).

**Files:**
- Modify: `src/Twig/Components/RelationManagers.php`
- Test: `tests/Functional/RelationManagerRenderTest.php`

- [ ] **Step 1: Failing test** — register a parent fixture with a relation `->visible(fn ($p) => false)`; assert the host renders no section for it.

- [ ] **Step 2: Run — expect failure**

- [ ] **Step 3: Implement**

Inject `DataProviderInterface` into `RelationManagers`; add:

```php
public function __construct(
    private readonly ResourceRegistry $registry,
    private readonly DataProviderInterface $dataProvider,
) {
}

public function getRelations(): array
{
    $parent = $this->loadParent();
    $views = [];
    foreach ($this->resourceObject()->relations() as $relation) {
        if (null !== $parent && !$relation->isVisibleFor($parent)) {
            continue;
        }
        $views[] = [
            'name' => $relation->getName(),
            'label' => $relation->getLabel(),
            'icon' => $relation->getIcon(),
        ];
    }

    return $views;
}

private function loadParent(): ?object
{
    $resource = $this->resourceObject();

    return $this->dataProvider->find(
        $resource->getEntityClass(),
        $this->parentId,
        $resource->scopeFilters(),
        $resource->getIdentifierField(),
    );
}
```

> Confirm `Relation::isVisibleFor(object $parent): bool` exists (REL-M1). If it defaults to a `true` closure, an unconfigured relation always shows.

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/RelationManagers.php tests/Functional/ tests/Fixtures/
git commit -m "Filter relation managers by visible(\$parent) in the host (REL-08)"
```

---

## Task 12: View page — read-only relation managers (`readOnlyOnView`)

Render `Atrium:RelationManagers` on the View page with `screen: 'view'`; with `readOnlyOnView` (default true) the managers show the list + pagination but no mutators (already enforced by `isReadOnly()` from Task 5).

**Files:**
- Modify: `templates/admin/view_page.html.twig`
- Test: `tests/Functional/RelationManagerActionsTest.php`

- [ ] **Step 1: Failing test**

```php
public function testViewScreenManagerIsReadOnly(): void
{
    $component = $this->createLiveComponent('Atrium:RelationManager', [
        'resource' => 'post', 'parentId' => '1', 'relation' => 'comments',
        'pathPrefix' => '/admin', 'screen' => 'view',
    ]);

    $html = $component->render()->toString();
    self::assertStringNotContainsString('Detach', $html);
    self::assertStringNotContainsString('Delete', $html);
    self::assertStringNotContainsString('New ', $html);
    // Read action / pagination still present (the list renders).
    self::assertStringContainsString('Great post', $html);
}
```

- [ ] **Step 2: Run — expect failure if `isReadOnly()` not yet honoured by every affordance.** Ensure `getHeaderActions`, `getRecordActions`, `getBulkActions`, `canCreateRelated`, `canAssociateRelated`, and `getRowAction` all short-circuit on `isReadOnly()` (Tasks 5/6/9/10 each guarded; verify the template triggers are also wrapped in `this.canCreateRelated` / `this.canAssociateRelated`).

- [ ] **Step 3: Wire the View page**

In `templates/admin/view_page.html.twig`, after the entries block (mirror `form_page.html.twig`'s embed, guarded by an id):

```twig
{% if entityId is defined and entityId %}
    {{ component('Atrium:RelationManagers', {
        resource: resource.slug,
        parentId: entityId,
        pathPrefix: panel.pathPrefix,
        screen: 'view',
    }) }}
{% endif %}
```

> Match the exact variable names the view template already exposes (`resource`, `entityId`, `panel`) — read the file first.

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add templates/admin/view_page.html.twig src/Twig/Components/RelationManager.php tests/Functional/RelationManagerActionsTest.php
git commit -m "Render read-only relation managers on the View page (REL-09)"
```

---

## Task 13: Docs + CHANGELOG

**Files:**
- Modify/Create: `docs/integration-guide/resources/relations.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Integration guide** — document, with copy-pasteable PHP:
  - The action set a one-to-many relation manager exposes: owned Create/Edit/Delete + Bulk Delete, Associate/Dissociate.
  - Inline modal Create/Edit (the parent FK is set automatically; one transaction).
  - The Associate picker (`listLinkable` excludes already-linked).
  - `readOnlyOnView` (default true) and `visible(fn ($parent) => …)`.
  - Authorization: owned actions gate on the **target** resource (`canCreate`/`canEdit`/`canDelete`); link/unlink gate on the **parent** resource's `canAssociate`/`canDissociate($parent, $child)`. Method table for both.
  - Follow the canonical page template in `docs/integration-guide/README.md` (summary, When to use, example, API reference).

- [ ] **Step 2: CHANGELOG** — `[Unreleased]` entry:
  - "Relation managers — one-to-many actions (REL-05, REL-06, REL-07, REL-09, REL-12): owned create/edit (inline modal)/delete + bulk delete, associate/dissociate with a `listLinkable` picker, read-only-on-view, `visible($parent)`."
  - **Public API (BC-relevant):** `AdminResource::canAssociate(object $parent, object $child): bool` and `canDissociate(...)` added (default-allow). `Form` mount gains `embedded`/`presetValues`/`notifyEvent` (additive, defaults preserve behaviour).

- [ ] **Step 3: Gates + commit**

```bash
composer test && composer phpstan && composer cs
git add docs/ CHANGELOG.md
git commit -m "Document one-to-many relation actions (REL-M2)"
```

---

## Self-review notes (checked against PRD §5.3/§5.5/§7 and REL-M2 acceptance)

- **Owned create/edit = inline modal** (PRD §5.3): Tasks 7–9, via nested `Atrium:Form` (no duplication of validation/rendering). Create sets the FK in one transaction (Form's own `transactional()`), satisfying "create sets the FK, in one transaction".
- **Associate/Dissociate + listLinkable picker** (REL-06 1:M, REL-07): Tasks 1, 2, 10, 6. `listLinkable` excludes already-linked via `FK IS NULL`.
- **readOnlyOnView + visible(fn) + emptyState** (REL-09): Tasks 5/12 (read-only) + 11 (visible). Per-relation `emptyState` already wired in REL-M1; no new task needed (note in docs).
- **canAssociate/canDissociate + owned target-ability gating, re-checked at execution** (REL-12): Task 3 (hooks), Task 6 (override routes link/unlink to parent resource; owned to target via base), all server actions re-check at execution (`executeAction`, `submitAssociate`, `openCreate/openEdit`).
- **Both adapters** (REL-10/11): Tasks 1–2 implement array + Doctrine; transactions share the writer's connection (Doctrine `flush()` enlists in the ambient `wrapInTransaction`).
- **No Doctrine in core**: only `DoctrineRelationProvider` touches Doctrine. `RelationManager`/`Form` use `DataProviderInterface`/`RelationDataProvider`/`DataWriterInterface` only.
- **Open question deferred to M3, noted not dropped:** Many-to-many `attach`/`detach`/pivot and the tab strip remain stubbed (LogicException) — REL-M3. The associate picker caps at 100/1000 linkable rows (a `log`/doc note): large-dataset search-paged picker is a future enhancement, acceptable for M2's native `Select`.
- **Type consistency check:** `getModalFormProps()`/`isModalOpen()`/`getRowAction()`/`canCreateRelated()`/`canAssociateRelated()`/`getSingularLabel()` are the names used in both component and template; `modalMode` values `'create'|'edit'|'associate'|null` are consistent across actions, listeners, and template guards; `refreshRecords()` is the shared cache-reset extracted from `executeAction`.

## Roadmap — REL-M3..M5 (each gets its own plan)

- **REL-M3 — many-to-many + tabs:** `manyToMany`/pivot in providers (join `listRelated`/`listLinkable`, `attach`/`detach` via DBAL sharing the writer transaction; array pivot list), pivot columns + attach-form fields, the host's server-driven tab strip (active-only mount, stable component key), `canAttach`/`canDetach`.
- **REL-M4 — nested resources:** `ParentRelation` + `AdminResource::parent()` + boot validation, nested route family + non-collision ordering, scoped parent resolution + cross-parent 404, `NestedActionContext`, `PageContext.parentRecords`/`nestedUrl()`, recursive breadcrumb, relation-manager row links → child nested pages.
- **REL-M5 — playground, docs, verify, review:** `->using()` example, playground (Post→Comments 1:M, Post↔Tags M:N, Course→Lessons nested), `docs/integration-guide/relations/{overview,nesting}.md`, CHANGELOG REL-01..22, browser-verify, separate code-review agent.
