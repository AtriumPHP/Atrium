# Relation Managers — REL-M3 (Many-to-Many + Tabs) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add many-to-many relation managers (Attach / Detach an existing record through a pivot table, with pivot-column form fields) and a server-driven **tab strip** host that mounts one manager at a time — completing the relation feature's link/unlink surface and multi-relation placement.

**Architecture:** The M:N data path resolves the parent's related child ids from the pivot table, then lists/counts the child *entities* by id through the ORM (so the child's `scopeQuery()`, pagination and sort still apply). The pivot is **never mapped as an entity** — `DoctrineRelationProvider` reads/writes it through DBAL on the `EntityManager`'s connection, so a pivot write inside the manager's `writer->transactional()` (Doctrine `wrapInTransaction`) shares that transaction. The array adapter keeps an in-memory pivot tuple list. `RelationManager` dispatches its action set by the relation **kind**: one-to-many keeps create/edit/delete + associate/dissociate (REL-M2); many-to-many gets **Attach** (a `listLinkable` picker plus pivot-column fields) + **Detach** + **bulk Detach**, gated by new parent-resource `canAttach`/`canDetach` hooks. The `RelationManagers` host becomes a Live Component holding `#[LiveProp] activeRelation`: one relation renders as a single section, several render as a tab strip that mounts only the active manager.

**Tech Stack:** PHP 8.4, Doctrine DBAL (adapter only — no new Doctrine in core), Symfony UX Live Components, PHPUnit, PHPStan max, PHP-CS-Fixer Symfony ruleset.

**Gates (run after every task):** `composer test && composer phpstan && composer cs`.

## Scope decisions (confirmed with the user)

1. **M:N = Attach / Detach only.** No owned Create/Edit/Delete on a many-to-many manager (you link existing records; mutating a shared child from a link list is surprising). One-to-many managers are unchanged.
2. **Pivot columns: persist now, display later.** The Attach form captures pivot-column values and stores them on the pivot row. Showing pivot columns *as table columns* needs new plumbing through the shared `AbstractRecordTable` renderer and is a deferred follow-up (noted in CHANGELOG/docs), not part of REL-M3.

## What already exists (REL-M1/M2 — do not rebuild)

- `Relation::manyToMany()/pivotTable()/pivotKeys()/pivotColumns()` + getters, and `RelationResolver` already validates M:N keys and fills `RelationDescriptor` (`pivotTable`, `pivotParentKey`, `pivotRelatedKey`, `pivotColumns`). `RelationKind::ManyToMany->usesPivot()` is `true`.
- `RelationManager` has the modal/Live-action machinery (open/close, `#[LiveListener]`, `actionAuthorized` override, `refreshRecords`, the associate picker pattern) and `AbstractRecordTable` has `getRowAction()`.
- `DoctrineRelationProvider` holds the `EntityManagerInterface` (→ `getConnection()` for DBAL). `DoctrineDataWriter::transactional()` = `wrapInTransaction` on the same connection.
- Both providers currently `throw new \LogicException(... REL-M3 ...)` for the M:N paths.

---

## File structure

**Data layer (adapters only — Doctrine confined to `DoctrineRelationProvider`):**
- `src/DataProvider/ArrayRelationProvider.php` — add an in-memory pivot store + M:N read/linkable/attach/detach.
- `src/DataProvider/DoctrineRelationProvider.php` — M:N read/linkable via "pivot ids → child query", attach/detach via DBAL.

**Core contract (BC-relevant — flag in CHANGELOG):**
- `src/Resource/AdminResource.php` — `canAttach(object $parent, object $child): bool` / `canDetach(...)` (default-allow).

**Components:**
- `src/Twig/Components/RelationManager.php` — kind dispatch (detach/bulk-detach actions, attach modal + pivot fields, `attach`/`detach` authorization, M:N row not clickable).
- `src/Twig/Components/RelationManagers.php` — become a Live Component: `activeRelation`, `selectTab`, active-only mount, single-section fallback.

**Templates:**
- `templates/components/relation_manager.html.twig` — Attach button + attach modal (Select + pivot fields).
- `templates/components/relation_managers.html.twig` — tab strip + active manager; single-section path.

**Tests & fixtures:**
- `tests/DataProvider/{Array,Doctrine}RelationProviderTest.php` — M:N read/linkable/attach/detach.
- `tests/Functional/RelationManagerManyToManyTest.php` (new) — attach/detach/bulk-detach, auth, read-only.
- `tests/Functional/RelationManagersTabsTest.php` (new) — tab strip selection + active-only mount. (Or extend the existing `tests/Twig/Components/RelationManagersTest.php` unit test for the non-Live filtering, and add a functional test for tabs.)
- Fixtures: a Doctrine M:N pair (`Course` ↔ `Student`, pivot `course_student`) for provider tests; an array M:N (`Post` ↔ `Tag`, pivot `post_tag`) reusing existing `Post`/`Tag` plain entities for functional tests; `PostTagsResource` (parent, `manyToMany`) + `TagRelResource` (target).

---

## Conventions

- **Doctrine M:N read pattern:** `relatedIds(descriptor, parent)` runs one DBAL query
  `SELECT <pivotRelatedKey> FROM <pivotTable> WHERE <pivotParentKey> = ?` (table/column
  names from the trusted descriptor; the value bound as a parameter). `listRelated`
  then runs an ORM query on the child repo `WHERE r.<childIdField> IN (:ids)` plus the
  scope filters (`applyFilters`), pagination and sort — empty ids short-circuit to no
  rows. `listLinkable` is the same with `NOT IN (:ids)` (no condition when ids empty).
- **Array M:N pivot store:** constructor gains `array<string, list<array{parent: scalar|null, related: scalar|null, columns: array<string, scalar|null>}>>` keyed by pivot table name.
- **Kind dispatch in the manager:** `private function isManyToMany(): bool { return RelationKind::ManyToMany === $this->descriptor()->kind; }`.

---

## Task 1: Providers — many-to-many read (`listRelated` / `countRelated`)

**Files:**
- Modify: `src/DataProvider/ArrayRelationProvider.php`, `src/DataProvider/DoctrineRelationProvider.php`
- Create: `tests/Fixtures/Entity/Course.php`, `tests/Fixtures/Entity/Student.php`
- Test: `tests/DataProvider/ArrayRelationProviderTest.php`, `tests/DataProvider/DoctrineRelationProviderTest.php`

- [ ] **Step 1: Doctrine fixtures.** Create two ORM entities with no association mapping between them:

```php
// tests/Fixtures/Entity/Course.php
#[ORM\Entity] #[ORM\Table(name: 'courses')]
class Course
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] public ?int $id = null;
    public function __construct(#[ORM\Column] public string $title = '') {}
}
```
```php
// tests/Fixtures/Entity/Student.php
#[ORM\Entity] #[ORM\Table(name: 'students')]
class Student
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] public ?int $id = null;
    public function __construct(#[ORM\Column] public string $name = '') {}
}
```

- [ ] **Step 2: Failing array test.** In `ArrayRelationProviderTest`, add an M:N descriptor helper and a test. The array provider's M:N store is a pivot tuple list:

```php
private function pivotDescriptor(): RelationDescriptor
{
    return new RelationDescriptor(
        name: 'tags', kind: RelationKind::ManyToMany,
        childEntityClass: Tag::class, childIdField: 'id', parentIdField: 'id',
        recordTitleAttribute: 'name',
        pivotTable: 'post_tag', pivotParentKey: 'post_id', pivotRelatedKey: 'tag_id',
        pivotColumns: ['note'],
    );
}

public function testManyToManyListRelatedJoinsThroughThePivot(): void
{
    $post = new Post(1, 'First');
    $provider = new ArrayRelationProvider(
        [Tag::class => [new Tag(1, 'A', 'a'), new Tag(2, 'B', 'b'), new Tag(3, 'C', 'c')]],
        pivots: ['post_tag' => [
            ['parent' => 1, 'related' => 1, 'columns' => []],
            ['parent' => 1, 'related' => 3, 'columns' => []],
            ['parent' => 2, 'related' => 2, 'columns' => []],
        ]],
    );

    $rows = [...$provider->listRelated($this->pivotDescriptor(), $post, new DataQuery())];
    self::assertSame([1, 3], array_map(static fn (Tag $t): int => $t->id, $rows));
    self::assertSame(2, $provider->countRelated($this->pivotDescriptor(), $post, new DataQuery()));
}
```

> `Tag`'s constructor is `new Tag(id, name, slug, active = false, kind = '')` — adjust the args to the real signature. Pass the `pivots` named arg (added in Step 4).

- [ ] **Step 3: Run — expect failure** (`LogicException` / unknown `pivots` arg).

- [ ] **Step 4: Implement array M:N read.** Add the pivot store and dispatch `matchingChildren`/count by kind:

```php
/** @param array<string, list<array{parent: scalar|null, related: scalar|null, columns: array<string, scalar|null>}>> $pivots */
public function __construct(
    private array $records = [],
    ?PropertyAccessorInterface $accessor = null,
    private array $pivots = [],
) {
    $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
}
```

Rework `listRelated`/`countRelated` to branch on kind. Keep the 1:M path (`matchingChildren`) and add an M:N path:

```php
public function listRelated(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
{
    $matched = $relation->kind->usesPivot()
        ? $this->relatedThroughPivot($relation, $parent, $query)
        : $this->matchingChildren($relation, $parent, $query);

    return \array_slice($matched, max(0, $query->offset), max(1, $query->limit));
}
// countRelated: same branch, return \count(...)

/** @return list<object> */
private function relatedThroughPivot(RelationDescriptor $relation, object $parent, DataQuery $query): array
{
    $relatedIds = $this->pivotRelatedIds($relation, $parent);
    $children = array_values(array_filter(
        $this->records[$relation->childEntityClass] ?? [],
        fn (object $child): bool => \in_array($this->childId($child, $relation), $relatedIds, false),
    ));

    return $this->applyArrayFilters($children, $query);   // extract the existing filter loop into a helper
}

/** @return list<scalar|null> */
private function pivotRelatedIds(RelationDescriptor $relation, object $parent): array
{
    $parentId = $this->parentId($relation, $parent);
    $ids = [];
    foreach ($this->pivots[(string) $relation->pivotTable] ?? [] as $row) {
        if ($row['parent'] === $parentId) {
            $ids[] = $row['related'];
        }
    }

    return $ids;
}

private function childId(object $child, RelationDescriptor $relation): mixed
{
    return $this->accessor->getValue($child, $relation->childIdField);
}
```

> Extract the `foreach ($query->filters ...)` block already duplicated in `matchingChildren`/`linkableChildren` into one `applyArrayFilters(array $children, DataQuery $query): array` helper and call it from all three paths (DRY). Use loose comparison (`false` third arg to `in_array`) so an int id matches a string pivot value, consistent with the rest of the array adapter's scalar handling.

- [ ] **Step 5: Run array test — expect PASS.**

- [ ] **Step 6: Failing Doctrine test.** In `DoctrineRelationProviderTest`, build the pivot table in `setUp` (it is not a mapped entity, so `SchemaTool` won't create it):

```php
// in setUp, after createSchema for Course + Student:
$this->entityManager->getConnection()->executeStatement(
    'CREATE TABLE course_student (course_id INTEGER NOT NULL, student_id INTEGER NOT NULL, role VARCHAR(255) DEFAULT NULL)'
);
```

```php
private function pivotDescriptor(): RelationDescriptor
{
    return new RelationDescriptor(
        name: 'students', kind: RelationKind::ManyToMany,
        childEntityClass: Student::class, childIdField: 'id', parentIdField: 'id',
        recordTitleAttribute: 'name',
        pivotTable: 'course_student', pivotParentKey: 'course_id', pivotRelatedKey: 'student_id',
        pivotColumns: ['role'],
    );
}

public function testManyToManyListRelatedJoinsThroughThePivot(): void
{
    $course = new Course('Math'); $this->entityManager->persist($course);
    $s1 = new Student('Ada'); $s2 = new Student('Bo'); $s3 = new Student('Cy');
    foreach ([$s1, $s2, $s3] as $s) { $this->entityManager->persist($s); }
    $this->entityManager->flush();

    $conn = $this->entityManager->getConnection();
    $conn->insert('course_student', ['course_id' => $course->id, 'student_id' => $s1->id]);
    $conn->insert('course_student', ['course_id' => $course->id, 'student_id' => $s3->id]);
    $this->entityManager->clear();

    $parent = $this->entityManager->find(Course::class, $course->id);
    self::assertNotNull($parent);

    $rows = [...$this->provider->listRelated($this->pivotDescriptor(), $parent, new DataQuery())];
    self::assertCount(2, $rows);
    self::assertContainsOnlyInstancesOf(Student::class, $rows);
    self::assertSame(2, $this->provider->countRelated($this->pivotDescriptor(), $parent, new DataQuery()));
}
```

- [ ] **Step 7: Run — expect failure.**

- [ ] **Step 8: Implement Doctrine M:N read.** Branch `listRelated`/`countRelated` by kind; keep the 1:M `relatedQuery`. Add:

```php
public function listRelated(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
{
    if ($relation->kind->usesPivot()) {
        return $this->childrenByIds($relation, $this->pivotRelatedIds($relation, $parent), $query, false);
    }
    // …existing 1:M body…
}

public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int
{
    if ($relation->kind->usesPivot()) {
        $ids = $this->pivotRelatedIds($relation, $parent);
        return [] === $ids ? 0 : $this->countByIds($relation, $ids, $query, false);
    }
    // …existing 1:M body…
}

/** @return list<scalar> related child ids linked to $parent through the pivot. */
private function pivotRelatedIds(RelationDescriptor $relation, object $parent): array
{
    $parentId = $this->accessor->getValue($parent, $relation->parentIdField);
    $sql = \sprintf('SELECT %s FROM %s WHERE %s = ?', $relation->pivotRelatedKey, $relation->pivotTable, $relation->pivotParentKey);

    return array_values(array_filter(
        $this->entityManager->getConnection()->fetchFirstColumn($sql, [$parentId]),
        static fn (mixed $v): bool => \is_scalar($v),
    ));
}

/**
 * Children whose id is IN (or NOT IN, when $exclude) the given id set, with the
 * target scope filters, pagination and sort applied. Empty $ids → no rows for the
 * IN case; for NOT-IN an empty set means "all children" (no id condition).
 * @param list<scalar> $ids
 * @return list<object>
 */
private function childrenByIds(RelationDescriptor $relation, array $ids, DataQuery $query, bool $exclude): array
{
    if (!$exclude && [] === $ids) {
        return [];
    }
    $qb = $this->idScopedQuery($relation, $ids, $exclude, $query)
        ->setFirstResult(max(0, $query->offset))
        ->setMaxResults(max(1, $query->limit));

    return array_values(array_filter((array) $qb->getQuery()->getResult(), static fn (mixed $r): bool => \is_object($r)));
}

/** @param list<scalar> $ids */
private function countByIds(RelationDescriptor $relation, array $ids, DataQuery $query, bool $exclude): int
{
    if (!$exclude && [] === $ids) {
        return 0;
    }
    $qb = $this->idScopedQuery($relation, $ids, $exclude, $query)->select(\sprintf('COUNT(%s)', self::ALIAS));

    return (int) $qb->getQuery()->getSingleScalarResult();
}

/** @param list<scalar> $ids */
private function idScopedQuery(RelationDescriptor $relation, array $ids, bool $exclude, DataQuery $query): QueryBuilder
{
    $qb = $this->entityManager->getRepository($relation->childEntityClass)->createQueryBuilder(self::ALIAS);
    if ([] !== $ids) {
        $field = self::ALIAS.'.'.$relation->childIdField;
        $qb->where($exclude ? $qb->expr()->notIn($field, ':atrium_ids') : $qb->expr()->in($field, ':atrium_ids'))
           ->setParameter('atrium_ids', $ids);
    }
    $this->applyFilters($qb, $query->filters);

    return $qb;
}
```

> Fix the typo: the method is `childrenByIds` (used by both `listRelated` and the count/linkable paths). `applyFilters` already exists (REL-M2).

- [ ] **Step 9: Run both — expect PASS.**

- [ ] **Step 10: Gates + commit.**

```bash
composer test && composer phpstan && composer cs
git add src/DataProvider/ tests/DataProvider/ tests/Fixtures/Entity/
git commit -m "Many-to-many listRelated/countRelated via pivot ids (REL-02, REL-10)"
```

---

## Task 2: Providers — many-to-many `listLinkable` / `countLinkable`

Linkable M:N candidates are children **not** already linked to this parent: `id NOT IN (linked ids)`, plus the target scope and pagination.

**Files:** both providers + both provider tests.

- [ ] **Step 1: Failing array test.**

```php
public function testManyToManyListLinkableExcludesAlreadyLinked(): void
{
    $post = new Post(1, 'First');
    $provider = new ArrayRelationProvider(
        [Tag::class => [new Tag(1, 'A', 'a'), new Tag(2, 'B', 'b'), new Tag(3, 'C', 'c')]],
        pivots: ['post_tag' => [['parent' => 1, 'related' => 1, 'columns' => []]]],
    );

    $rows = [...$provider->listLinkable($this->pivotDescriptor(), $post, new DataQuery())];
    self::assertSame([2, 3], array_map(static fn (Tag $t): int => $t->id, $rows));
    self::assertSame(2, $provider->countLinkable($this->pivotDescriptor(), $post, new DataQuery()));
}
```

- [ ] **Step 2: Run — expect failure** (currently `linkableChildren` asserts one-to-many).

- [ ] **Step 3: Implement array M:N linkable.** Branch `listLinkable`/`countLinkable` by kind:

```php
private function linkableThroughPivot(RelationDescriptor $relation, object $parent, DataQuery $query): array
{
    $linkedIds = $this->pivotRelatedIds($relation, $parent);
    $children = array_values(array_filter(
        $this->records[$relation->childEntityClass] ?? [],
        fn (object $child): bool => !\in_array($this->childId($child, $relation), $linkedIds, false),
    ));

    return $this->applyArrayFilters($children, $query);
}
```

Route `listLinkable`/`countLinkable` through it when `$relation->kind->usesPivot()`, else the existing `linkableChildren`.

- [ ] **Step 4: Run — expect PASS.**

- [ ] **Step 5: Failing Doctrine test** (pivot built in setUp from Task 1):

```php
public function testManyToManyListLinkableExcludesAlreadyLinked(): void
{
    $course = new Course('Math'); $this->entityManager->persist($course);
    $s1 = new Student('Ada'); $s2 = new Student('Bo');
    foreach ([$s1, $s2] as $s) { $this->entityManager->persist($s); }
    $this->entityManager->flush();
    $this->entityManager->getConnection()->insert('course_student', ['course_id' => $course->id, 'student_id' => $s1->id]);
    $this->entityManager->clear();

    $parent = $this->entityManager->find(Course::class, $course->id);
    self::assertNotNull($parent);
    $rows = [...$this->provider->listLinkable($this->pivotDescriptor(), $parent, new DataQuery())];
    self::assertCount(1, $rows);
    self::assertSame('Bo', $rows[0]->name);
    self::assertSame(1, $this->provider->countLinkable($this->pivotDescriptor(), $parent, new DataQuery()));
}
```

- [ ] **Step 6: Run — expect failure.**

- [ ] **Step 7: Implement Doctrine M:N linkable** — branch `listLinkable`/`countLinkable`:

```php
public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
{
    if ($relation->kind->usesPivot()) {
        return $this->childrenByIds($relation, $this->pivotRelatedIds($relation, $parent), $query, exclude: true);
    }
    // …existing 1:M body…
}
// countLinkable: M:N → countByIds(..., exclude: true)
```

- [ ] **Step 8: Run both — expect PASS. Gates + commit.**

```bash
git commit -m "Many-to-many listLinkable/countLinkable (NOT IN pivot) (REL-07, REL-10)"
```

---

## Task 3: Providers — many-to-many `attach` / `detach`

`attach` inserts a pivot row (parent key, related key, pivot columns); `detach` deletes it. Doctrine uses DBAL on the EM connection (enlists in the ambient `wrapInTransaction`); the array adapter mutates its tuple list.

**Files:** both providers + both provider tests.

- [ ] **Step 1: Failing array test.**

```php
public function testManyToManyAttachAddsPivotRowAndDetachRemovesIt(): void
{
    $post = new Post(1, 'First');
    $tag = new Tag(5, 'New', 'new');
    $provider = new ArrayRelationProvider([Tag::class => [$tag]], pivots: ['post_tag' => []]);

    $provider->attach($this->pivotDescriptor(), $post, $tag, ['note' => 'hi']);
    self::assertSame([5], array_map(static fn (Tag $t): int => $t->id, [...$provider->listRelated($this->pivotDescriptor(), $post, new DataQuery())]));

    $provider->detach($this->pivotDescriptor(), $post, $tag);
    self::assertCount(0, [...$provider->listRelated($this->pivotDescriptor(), $post, new DataQuery())]);
}
```

- [ ] **Step 2: Run — expect failure** (`LogicException`).

- [ ] **Step 3: Implement array attach/detach.**

```php
public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void
{
    $this->assertManyToMany($relation);
    $this->pivots[(string) $relation->pivotTable][] = [
        'parent' => $this->scalarParentId($relation, $parent),
        'related' => $this->scalarChildId($relation, $child),
        'columns' => $pivot,
    ];
}

public function detach(RelationDescriptor $relation, object $parent, object $child): void
{
    $this->assertManyToMany($relation);
    $table = (string) $relation->pivotTable;
    $parentId = $this->scalarParentId($relation, $parent);
    $relatedId = $this->scalarChildId($relation, $child);
    $this->pivots[$table] = array_values(array_filter(
        $this->pivots[$table] ?? [],
        static fn (array $row): bool => !($row['parent'] === $parentId && $row['related'] === $relatedId),
    ));
}
```

> Add `assertManyToMany()` (mirror `assertOneToMany`) and small `scalarParentId`/`scalarChildId` helpers returning `is_scalar ? value : null`.

- [ ] **Step 4: Run — expect PASS.**

- [ ] **Step 5: Failing Doctrine test** (asserts the row lands and a pivot column is stored, inside a transaction):

```php
public function testManyToManyAttachInsertsPivotRowWithColumnsAndDetachRemovesIt(): void
{
    $course = new Course('Math'); $student = new Student('Ada');
    $this->entityManager->persist($course); $this->entityManager->persist($student);
    $this->entityManager->flush();

    $this->provider->attach($this->pivotDescriptor(), $course, $student, ['role' => 'lead']);

    $conn = $this->entityManager->getConnection();
    self::assertSame('lead', $conn->fetchOne('SELECT role FROM course_student WHERE course_id = ? AND student_id = ?', [$course->id, $student->id]));

    $this->provider->detach($this->pivotDescriptor(), $course, $student);
    self::assertFalse($conn->fetchOne('SELECT role FROM course_student WHERE course_id = ? AND student_id = ?', [$course->id, $student->id]));
}
```

- [ ] **Step 6: Run — expect failure.**

- [ ] **Step 7: Implement Doctrine attach/detach** (DBAL; column/table from the trusted descriptor, values bound):

```php
public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void
{
    $this->assertManyToMany($relation);
    $data = [
        (string) $relation->pivotParentKey => $this->accessor->getValue($parent, $relation->parentIdField),
        (string) $relation->pivotRelatedKey => $this->accessor->getValue($child, $relation->childIdField),
    ];
    foreach ($relation->pivotColumns as $column) {
        $data[$column] = $pivot[$column] ?? null;
    }
    $this->entityManager->getConnection()->insert((string) $relation->pivotTable, $data);
}

public function detach(RelationDescriptor $relation, object $parent, object $child): void
{
    $this->assertManyToMany($relation);
    $this->entityManager->getConnection()->delete((string) $relation->pivotTable, [
        (string) $relation->pivotParentKey => $this->accessor->getValue($parent, $relation->parentIdField),
        (string) $relation->pivotRelatedKey => $this->accessor->getValue($child, $relation->childIdField),
    ]);
}
```

> `Connection::insert`/`delete` quote identifiers and bind all values as parameters. Add `assertManyToMany()` (throws for one-to-many, mirroring the existing `kind` guards). When called inside `RelationManager`'s `writer->transactional()`, these run on the same connection inside the open transaction.

- [ ] **Step 8: Run both — expect PASS. Gates + commit.**

```bash
git commit -m "Many-to-many attach/detach via DBAL pivot writes (REL-07, REL-10)"
```

---

## Task 4: `AdminResource` — `canAttach` / `canDetach` hooks (BC-relevant)

Mirror `canAssociate`/`canDissociate` (REL-M2). Default-allow; the relation manager calls them directly with `($parent, $child)`.

**Files:** `src/Resource/AdminResource.php`, `tests/Resource/ResourceHooksTest.php`.

- [ ] **Step 1: Failing test** (extend the existing relation-abilities test or add one):

```php
public function testManyToManyLinkAbilitiesDefaultAllowAndAreOverridable(): void
{
    $resource = new class extends AdminResource {
        public function getEntityClass(): string { return Tag::class; }
        public function canDetach(object $parent, object $child): bool { return false; }
    };
    self::assertTrue($resource->canAttach(new \stdClass(), new \stdClass()));
    self::assertFalse($resource->canDetach(new \stdClass(), new \stdClass()));
}
```

- [ ] **Step 2: Run — expect failure.**

- [ ] **Step 3: Implement** (next to `canAssociate`/`canDissociate`):

```php
/**
 * Whether $child may be attached to $parent through a many-to-many relation
 * (insert a pivot row). Default-allow; override to restrict. Public API (REL-12).
 */
public function canAttach(object $parent, object $child): bool
{
    return true;
}

/**
 * Whether $child may be detached from $parent (remove the pivot row; both records
 * persist). Default-allow; override to restrict. Public API (REL-12).
 */
public function canDetach(object $parent, object $child): bool
{
    return true;
}
```

Extend the `can()` docblock line to mention `canAttach`/`canDetach` alongside associate/dissociate.

- [ ] **Step 4: Run — expect PASS. Gates + CHANGELOG flag + commit.**

Add to the existing `[Unreleased]` REL public-API note: `canAttach`/`canDetach` added.

```bash
git commit -m "Add canAttach/canDetach authorization hooks (REL-12, public API)"
```

---

## Task 5: `RelationManager` — many-to-many Detach + bulk Detach + kind dispatch

Dispatch the action set by kind. M:N: row **Detach**, **bulk Detach**, no owned create/edit/delete, row not clickable. Authorization for `attach`/`detach` routes to the parent resource.

**Files:** `src/Twig/Components/RelationManager.php`, `tests/Functional/RelationManagerManyToManyTest.php` (new), fixtures.

- [ ] **Step 1: Fixtures.** Create the functional M:N pair reusing existing plain `Post`/`Tag`:

```php
// tests/Fixtures/Resource/TagRelResource.php  (M:N target)
final class TagRelResource extends AdminResource
{
    public function getEntityClass(): string { return Tag::class; }
    public function getSlug(): string { return 'tag-rel'; }
    public function table(TableConfiguration $t): TableConfiguration { return $t->columns([Column::make('name')]); }
}
```
```php
// tests/Fixtures/Resource/PostTagsResource.php  (parent with a M:N relation)
final class PostTagsResource extends AdminResource
{
    public function getEntityClass(): string { return Post::class; }
    public function getSlug(): string { return 'post-tags'; }
    public function table(TableConfiguration $t): TableConfiguration { return $t->columns([Column::make('title')]); }
    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('tags')
                ->manyToMany(TagRelResource::class)
                ->pivotTable('post_tag')->pivotKeys('post_id', 'tag_id')
                ->pivotColumns(['note'])
                ->recordTitle('name'),
        ];
    }
}
```

Seed the array pivot in `SampleData` and register both resources + the M:N alias in `AtriumTestKernel`; bump `KernelBootTest`'s count by 2. `SampleData::relationProvider()` passes the `pivots` arg:

```php
// SampleData: add a tags list + a post_tag pivot, sharing instances
$this->tags  // already exists (12 Tag objects)
// relationProvider():
return new ArrayRelationProvider([Comment::class => $this->comments, Tag::class => $this->tags], pivots: [
    'post_tag' => [
        ['parent' => 1, 'related' => 1, 'columns' => []],
        ['parent' => 1, 'related' => 2, 'columns' => []],
    ],
]);
```

> Post 1 is linked to tags 1 and 2; tags 3+ are linkable. (Confirm the Tag list has ≥3 entries — it has 12.)

- [ ] **Step 2: Failing test** — detach unlinks; bulk detach; M:N has no Delete/Edit/New:

```php
public function testManyToManyDetachUnlinksTheTag(): void
{
    $component = $this->createLiveComponent('Atrium:RelationManager', [
        'resource' => 'post-tags', 'parentId' => '1', 'relation' => 'tags', 'pathPrefix' => '/admin',
    ]);
    $html = $component->render()->toString();
    self::assertStringContainsString('Tag 01', $html);
    self::assertStringContainsString('Detach', $html);
    self::assertStringNotContainsString('Delete', $html);   // no owned delete on M:N

    $component->call('requestAction', ['name' => 'detach', 'id' => '1']);
    $component->call('confirmAction');

    self::assertStringNotContainsString('Tag 01', $component->render()->toString());
}
```

- [ ] **Step 3: Run — expect failure** (no `detach` action; M:N path throws in providers via the manager? No — providers done; the manager still emits 1:M actions).

- [ ] **Step 4: Implement kind dispatch.** Add `isManyToMany()` and branch the action accessors:

```php
public function getRecordActions(): array
{
    if ($this->isReadOnly()) { return []; }
    return $this->isManyToMany() ? [$this->detachAction()] : [$this->dissociateAction(), DeleteAction::make()];
}

public function getBulkActions(): array
{
    if ($this->isReadOnly()) { return []; }
    return $this->isManyToMany() ? [$this->bulkDetachAction()] : [BulkDeleteAction::make()];
}

private function detachAction(): Action
{
    $descriptor = $this->descriptor();
    $parent = $this->parent();
    return Action::make('detach')
        ->label('Detach')->icon('unlink')->color('gray')
        ->authorize('detach')
        ->confirmationMessage('Detach this record? The link is removed; the record itself is kept.')
        ->action(function (object $child) use ($descriptor, $parent): void {
            $this->relationProvider->detach($descriptor, $parent, $child);
        });
}

private function bulkDetachAction(): Action
{
    $descriptor = $this->descriptor();
    $parent = $this->parent();
    return Action::make('detachSelected')
        ->label('Detach selected')->icon('unlink')->color('gray')
        ->authorize('detach')
        ->confirmationMessage('Detach the selected records?')
        ->action(function (array $children) use ($descriptor, $parent): void {
            foreach ($children as $child) {
                $this->relationProvider->detach($descriptor, $parent, $child);
            }
        });
}

private function isManyToMany(): bool
{
    return RelationKind::ManyToMany === $this->descriptor()->kind;
}
```

Extend `actionAuthorized` with the M:N abilities:

```php
return match ($action->getAbility()) {
    'associate' => null !== $record && $this->parentResource()->canAssociate($this->parent(), $record),
    'dissociate' => null !== $record && $this->parentResource()->canDissociate($this->parent(), $record),
    'attach' => null !== $record && $this->parentResource()->canAttach($this->parent(), $record),
    'detach' => null !== $record && $this->parentResource()->canDetach($this->parent(), $record),
    default => parent::actionAuthorized($action, $record),
};
```

Make M:N rows non-clickable and exclude M:N from owned-create:

```php
public function getRowAction(): ?string
{
    return ($this->isReadOnly() || $this->isManyToMany()) ? null : 'openEdit';
}

public function canCreateRelated(): bool
{
    return !$this->isReadOnly() && !$this->isManyToMany() && $this->target()->canCreate();
}
```

> Import `RelationKind`. `BulkDeleteAction`/`DeleteAction` already imported. The bulk handler receives `array $children` — `runBulkAction` calls `$handler($records, $this->writer)`; the extra writer arg is ignored, and per-record `canDetach` gating happens in `runBulkAction`'s `actionAuthorized` filter.

- [ ] **Step 5: Run — expect PASS.** Add a parent-denies-detach test (new `DenyDetachPostTagsResource` fixture, `canDetach` false; +1 to KernelBoot count): assert the row has no "Detach" and a forced `requestAction('detach','1')` leaves `confirmingAction` null.

- [ ] **Step 6: Gates + commit.**

```bash
git commit -m "Many-to-many Detach + bulk Detach with kind dispatch (REL-06, REL-12)"
```

---

## Task 6: `RelationManager` — Attach modal (picker + pivot fields)

A header **Attach existing** opens a modal: a `Select` over `listLinkable` plus a text input per pivot column. Submit calls `attach` with the pivot values, gated by `canAttach`.

**Files:** `src/Twig/Components/RelationManager.php`, `templates/components/relation_manager.html.twig`, the M:N functional test.

- [ ] **Step 1: Failing test.**

```php
public function testManyToManyAttachLinksATagWithPivotData(): void
{
    $component = $this->createLiveComponent('Atrium:RelationManager', [
        'resource' => 'post-tags', 'parentId' => '1', 'relation' => 'tags', 'pathPrefix' => '/admin',
    ]);
    $component->call('openAttach');
    self::assertSame('attach', $component->component()->modalMode);

    $component->set('attachId', '3');            // Tag 03 is linkable
    $component->set('pivotData', ['note' => 'primary']);
    $component->call('submitAttach');

    self::assertNull($component->component()->modalMode);
    self::assertStringContainsString('Tag 03', $component->render()->toString());
}
```

- [ ] **Step 2: Run — expect failure** (no `openAttach`).

- [ ] **Step 3: Implement the attach flow** (mirror the associate picker, REL-M2):

```php
#[LiveProp(writable: true)] public string $attachId = '';
/** @var array<string, string> */
#[LiveProp(writable: true)] public array $pivotData = [];

#[LiveAction]
public function openAttach(): void
{
    if ($this->isReadOnly() || !$this->isManyToMany()) { return; }
    $this->modalMode = 'attach';
    $this->attachId = '';
    $this->pivotData = [];
}

#[LiveAction]
public function submitAttach(): void
{
    if ($this->isReadOnly() || !$this->isManyToMany() || '' === $this->attachId) { return; }
    $child = $this->findLinkable($this->attachId);
    if (null === $child || !$this->parentResource()->canAttach($this->parent(), $child)) { return; }

    $pivot = $this->sanitisedPivot();
    $this->writer->transactional(function () use ($child, $pivot): void {
        $this->relationProvider->attach($this->descriptor(), $this->parent(), $child, $pivot);
    });

    $this->closeModal();
    $this->refreshRecords();
}

public function canAttachRelated(): bool
{
    return !$this->isReadOnly() && $this->isManyToMany();
}

/** @return list<string> */
public function getPivotColumns(): array
{
    return $this->descriptor()->pivotColumns;
}

/** @return array<string, scalar|null> only the declared pivot columns, as strings. */
private function sanitisedPivot(): array
{
    $clean = [];
    foreach ($this->descriptor()->pivotColumns as $column) {
        $value = $this->pivotData[$column] ?? null;
        $clean[$column] = \is_scalar($value) ? (string) $value : null;
    }
    return $clean;
}
```

Extend `closeModal()` to also reset `attachId`/`pivotData`. Reuse the existing `findLinkable()` (kind-agnostic — it iterates `listLinkable`, which now branches by kind in the provider).

- [ ] **Step 4: Attach modal + trigger in the template.** Add an Attach button (shown when `this.canAttachRelated`) next to the existing Associate/New block, and an attach modal mirroring the associate modal plus one input per pivot column:

```twig
{% if this.canAttachRelated %}
    <button type="button" data-action="live#action" data-live-action-param="openAttach"
            class="…secondary button classes…">{{ atrium_icon('link', { class: 'h-4 w-4' }) }} Attach existing</button>
{% endif %}
```
```twig
{% if this.isModalOpen and this.modalMode == 'attach' %}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <button type="button" data-action="live#action" data-live-action-param="closeModal" aria-label="Close" class="absolute inset-0 bg-black/40"></button>
        <div role="dialog" aria-modal="true" class="relative z-10 w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold …">Attach existing {{ this.singularLabel }}</h2>
            <select data-model="attachId" class="…select classes…">
                <option value="">—</option>
                {% for value, label in this.getLinkableOptions %}<option value="{{ value }}">{{ label }}</option>{% endfor %}
            </select>
            {% for column in this.getPivotColumns %}
                <label class="mt-3 block text-sm font-medium …">{{ column }}
                    <input type="text" data-model="pivotData.{{ column }}" class="mt-1 …input classes…">
                </label>
            {% endfor %}
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" data-action="live#action" data-live-action-param="closeModal" class="…">Cancel</button>
                <button type="button" data-action="live#action" data-live-action-param="submitAttach" class="…primary…">Attach</button>
            </div>
        </div>
    </div>
{% endif %}
```

> `data-model="pivotData.{{ column }}"` binds into the `pivotData` array (Live Components support dotted model paths on a writable array prop). Copy the exact input/select/button classes from the existing associate modal.

- [ ] **Step 5: Run — expect PASS.** Add: attach refused when `canAttach` is false (deny fixture); attach is a no-op with empty `attachId`.

- [ ] **Step 6: Gates + commit.**

```bash
git commit -m "Many-to-many Attach picker with pivot-column fields (REL-07, REL-12)"
```

---

## Task 7: `RelationManagers` host — server-driven tab strip

Turn the host into a Live Component holding `activeRelation`. One relation → a single titled section (today's behaviour). Several → a tab strip of buttons; only the **active** manager is mounted, with a stable key so its own re-renders are addressed correctly.

**Files:** `src/Twig/Components/RelationManagers.php`, `templates/components/relation_managers.html.twig`, `tests/Functional/RelationManagersTabsTest.php` (new). The existing `tests/Twig/Components/RelationManagersTest.php` unit test stays valid (it calls `getRelations()` directly).

- [ ] **Step 1: Failing test.**

```php
final class RelationManagersTabsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;
    protected function tearDown(): void { parent::tearDown(); restore_exception_handler(); }

    public function testRendersTabStripAndMountsOnlyTheActiveManager(): void
    {
        // A parent with two relations renders a tab per relation; switching tabs
        // mounts the other manager.
        $component = $this->createLiveComponent('Atrium:RelationManagers', [
            'resource' => 'post-multi', 'parentId' => '1', 'pathPrefix' => '/admin',
        ]);
        $html = $component->render()->toString();
        self::assertStringContainsString('Comments', $html);  // tab labels
        self::assertStringContainsString('Tags', $html);
        self::assertStringContainsString('Great post', $html); // first relation active by default

        $component->call('selectTab', ['relation' => 'tags']);
        $html = $component->render()->toString();
        self::assertStringContainsString('Tag 01', $html);     // tags manager now mounted
    }
}
```

> Needs a `PostMultiRelResource` fixture (slug `post-multi`) declaring **both** a one-to-many `comments` and a many-to-many `tags` relation; register it (+1 KernelBoot count) and seed its pivot under `post_tag` for parent 1.

- [ ] **Step 2: Run — expect failure** (host is not a Live Component; no `selectTab`).

- [ ] **Step 3: Convert the host to a Live Component.**

```php
#[AsLiveComponent(name: 'Atrium:RelationManagers', template: '@Atrium/components/relation_managers.html.twig')]
final class RelationManagers
{
    use DefaultActionTrait;

    #[LiveProp] public string $resource = '';
    #[LiveProp] public string $parentId = '';
    #[LiveProp] public string $pathPrefix = '';
    #[LiveProp] public string $screen = 'edit';
    #[LiveProp(writable: true)] public string $activeRelation = '';

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
    ) {}

    #[LiveAction]
    public function selectTab(#[LiveArg] string $relation): void
    {
        if (\in_array($relation, array_map(static fn (array $r): string => $r['name'], $this->getRelations()), true)) {
            $this->activeRelation = $relation;
        }
    }

    /** The relation shown now: the selected one if still visible, else the first. */
    public function getActiveRelation(): ?string
    {
        $names = array_map(static fn (array $r): string => $r['name'], $this->getRelations());
        if ('' !== $this->activeRelation && \in_array($this->activeRelation, $names, true)) {
            return $this->activeRelation;
        }
        return $names[0] ?? null;
    }

    // getRelations(), loadParent(), hasRelations(), resourceObject() unchanged.
}
```

> Keep the existing `getRelations()`/`loadParent()`/`hasRelations()` bodies. Add imports for `AsLiveComponent`, `LiveProp`, `LiveAction`, `LiveArg`, `DefaultActionTrait`. The component is still rendered through `{{ component('Atrium:RelationManagers', {...}) }}` on the Edit/View pages — no page-template change needed (a Live Component renders the same way). Its `@internal` docblock should drop the "M1 renders each as a titled section" note and describe the tab strip.

- [ ] **Step 4: Template — single section vs tab strip.**

```twig
{% if this.hasRelations %}
    {% set relations = this.getRelations %}
    {% set active = this.activeRelation %}
    <div {{ attributes.defaults({ class: 'mt-8' }) }}>
        {% if relations|length > 1 %}
            <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700" role="tablist">
                {% for relation in relations %}
                    <button type="button" role="tab" data-action="live#action" data-live-action-param="selectTab"
                            data-live-relation-param="{{ relation.name }}"
                            class="-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium {{ relation.name == active ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">
                        {% if relation.icon %}{{ atrium_icon(relation.icon, { class: 'h-4 w-4' }) }}{% endif %}{{ relation.label }}
                    </button>
                {% endfor %}
            </div>
        {% endif %}

        {% for relation in relations if relation.name == active %}
            <section class="space-y-3">
                {% if relations|length == 1 %}
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {% if relation.icon %}{{ atrium_icon(relation.icon, { class: 'h-4 w-4' }) }}{% endif %}{{ relation.label }}
                    </h2>
                {% endif %}
                {# Stable key so each tab's manager is a distinct component; switching
                   tabs mounts the newly-active one fresh. #}
                {{ component('Atrium:RelationManager', {
                    resource: resource, parentId: parentId, relation: relation.name,
                    pathPrefix: pathPrefix, screen: screen,
                }, { key: parentId ~ ':' ~ relation.name }) }}
            </section>
        {% endfor %}
    </div>
{% endif %}
```

> Verify the `component()` third-argument keying mechanism against the installed `symfony/ux-twig-component` (it may be `{{ component(name, props) }}` with no third arg). If unsupported, render the active manager via the `<twig:Atrium:RelationManager key="…" …/>` HTML syntax instead, or set `id` in the props. The requirement: switching `activeRelation` must mount the newly-active manager as a fresh component, not morph the previous tab's manager into it. The plan-review will confirm the exact idiom.

- [ ] **Step 5: Run — expect PASS.** Verify the existing single-relation Edit page (`post`) still renders one section with no tab strip (`tests/Functional/RelationManagerRenderTest` and the form-page path stay green).

- [ ] **Step 6: Gates + commit.**

```bash
git commit -m "Server-driven tab strip host, active-only manager mount (REL-08)"
```

---

## Task 8: Documentation + CHANGELOG

**Files:** `docs/integration-guide/resources/relations.md`, `CHANGELOG.md`.

- [ ] **Step 1: Extend `relations.md`** — a Many-to-many section: `manyToMany()->pivotTable()->pivotKeys()->pivotColumns()`, the Attach/Detach action set, pivot-column form fields (and the note that **displaying** pivot columns is a later follow-up), `canAttach`/`canDetach`, and the tab strip when a resource has several relations. Add the new methods/hooks to the API tables.

- [ ] **Step 2: CHANGELOG** — extend the `[Unreleased]` relations entry: many-to-many managers (Attach/Detach + bulk Detach via a pivot table; `listLinkable` picker with pivot-column fields), the server-driven tab strip host; **Public API:** `canAttach`/`canDetach`. Note pivot-column *display* in the table is deferred.

- [ ] **Step 3: Gates + commit.**

```bash
git commit -m "Document many-to-many relations + tabs (REL-M3)"
```

---

## Self-review (checked against PRD §5.3/§5.5/§7 and the REL-M3 milestone)

- **M:N read (REL-02):** Tasks 1–2 — pivot ids → child query, so the target `scopeQuery()`, pagination and sort apply on the child; `listLinkable` excludes already-linked via `NOT IN`. Both adapters.
- **M:N write (REL-07):** Task 3 — DBAL `insert`/`delete` (no pivot entity), values parameter-bound, table/columns from the trusted descriptor; runs inside the manager's `transactional()` so create-free attach still commits atomically with any surrounding work.
- **Action taxonomy by kind (REL-06):** Task 5 — M:N → Detach + bulk Detach; 1:M unchanged. Owned create/edit/delete intentionally excluded from M:N (scope decision 1); M:N rows are not click-to-edit.
- **Attach picker + pivot fields (REL-07):** Task 6 — `listLinkable`-backed Select + a field per pivot column; submit re-validates the candidate and re-checks `canAttach`. Pivot-column *display* deferred (scope decision 2).
- **Authorization (REL-12):** Task 4 + Task 5 — `canAttach`/`canDetach` on the parent resource, hidden-and-refused, re-checked at execution; both BC-flagged.
- **Tabs (REL-08):** Task 7 — host becomes a Live Component; one relation = single section, several = tab strip mounting only the active manager with a stable key.
- **No Doctrine in core:** only `DoctrineRelationProvider` gains DBAL use; the manager/host/providers-interface stay Doctrine-free.
- **Deferred, noted not dropped:** pivot-column table display (needs shared-renderer plumbing); owned create/edit/delete on M:N; cross-tab state preservation (active-only mount reloads a tab fresh — acceptable). Nested resources are REL-M4; playground + browser-verify + final review are REL-M5.
- **Type-consistency check:** `isManyToMany()`, `pivotRelatedIds()`, `childrenByIds()`/`countByIds()`/`idScopedQuery()`, `applyArrayFilters()`, `sanitisedPivot()`, `getPivotColumns()`, `canAttachRelated()`, `getActiveRelation()`/`selectTab()` are the names used across component, provider and template.

## Roadmap — REL-M4..M5 (each gets its own plan)

- **REL-M4 — nested resources:** `ParentRelation` + `AdminResource::parent()` + boot validation; nested route family + non-collision ordering; scoped parent resolution + cross-parent 404; `NestedActionContext`; `PageContext.parentRecords`/`nestedUrl()`; recursive breadcrumb; relation-manager row links → child nested pages (this is where owned **View** navigation for M2 lands).
- **REL-M5 — playground, docs, verify, review:** `->using()` extraction example; playground (Post→Comments 1:M, Post↔Tags M:N, Course→Lessons nested); finalize `docs/integration-guide/relations/{overview,nesting}.md`; CHANGELOG REL-01..22; browser-verify; separate code-review agent.
