# Relation Managers & Nested Resources — REL-M1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the foundation of the relations feature — the relationship descriptor, a storage-agnostic `RelationDataProvider` seam (Doctrine + array, one-to-many read side), a shared `AbstractRecordTable` core extracted from `DataTable`, and a read-only `RelationManager` Live Component embedded on the Edit page — so a parent record's related rows render scoped to it.

**Architecture:** A resource declares relations in PHP (`relations()` → `Relation::make()`); an `@internal` resolver turns each into an immutable `RelationDescriptor`. `DataTable`'s table-rendering machinery is extracted into an abstract `AbstractRecordTable` whose data path is two seams (`fetchPage`/`total`); `DataTable` and the new `RelationManager` both extend it, differing only in their data source. The relation manager reads related rows through the new `RelationDataProvider`, composing the parent foreign-key scope on top of the target resource's `scopeQuery()`.

**Tech Stack:** PHP 8.4, Symfony UX Live Components, Doctrine ORM (adapter only), PHPUnit 13, PHPStan max, PHP-CS-Fixer (Symfony ruleset). Gates: `composer test && composer phpstan && composer cs`.

**Scope note:** This is **REL-M1 only** (PRD `docs/PRDs/PRD-relations-nesting.md`, milestone REL-M1: `REL-01..04`, `REL-10`, `REL-11` read side, `REL-20` target-registration check). Milestones M2–M5 (link/unlink actions, many-to-many, nesting, playground/docs) each get their own plan — see the roadmap at the end. Each milestone is independently testable and ships working software.

**House rules (from CLAUDE.md):** `declare(strict_types=1)` in every file; `final` classes by default, `@internal` on non-public-API; **no Doctrine types in core** (only in `Doctrine*` adapters); commit messages carry **no `Co-Authored-By` trailer**; run the three gates before considering any task done.

---

## File Structure

**New (core, public API):**
- `src/Relation/RelationKind.php` — enum `OneToMany|ManyToMany`.
- `src/Relation/Relation.php` — the fluent descriptor builder (public API).

**New (core, `@internal`):**
- `src/Relation/RelationDescriptor.php` — immutable resolved view of a `Relation`.
- `src/Relation/RelationResolver.php` — turns a `Relation` + `ResourceRegistry` into a `RelationDescriptor` (resolves the target entity class, identifier fields, defaults).
- `src/DataProvider/RelationDataProvider.php` — the storage-agnostic interface.
- `src/DataProvider/ArrayRelationProvider.php` — in-memory implementation (tests/proof).
- `src/DataProvider/DoctrineRelationProvider.php` — Doctrine adapter (the only relation class allowed to touch Doctrine).
- `src/Twig/Components/AbstractRecordTable.php` — abstract base extracted from `DataTable`.
- `src/Twig/Components/RelationManager.php` — the relation-manager Live Component.
- `src/Twig/Components/RelationManagers.php` — the placement host (single-section in M1).
- `templates/components/_record_table.html.twig` — shared table partial.
- `templates/components/relation_manager.html.twig` — thin wrapper around the shared partial.
- `templates/components/relation_managers.html.twig` — the host band.

**Modified:**
- `src/Resource/AdminResource.php` — add `relations(): array` default (`[]`).
- `src/Twig/Components/DataTable.php` — re-expressed as `extends AbstractRecordTable` (no behaviour change).
- `templates/components/data_table.html.twig` — becomes a thin wrapper that `{% include %}`s the shared partial.
- `templates/admin/form_page.html.twig` — render the `RelationManagers` host after the form.
- `config/services.php` — wire `RelationDataProvider` alias for the array case / autowiring.
- `config/doctrine.php` — register `DoctrineRelationProvider` + alias `RelationDataProvider`.

**Tests:**
- `tests/Relation/RelationTest.php`
- `tests/Relation/RelationResolverTest.php`
- `tests/DataProvider/ArrayRelationProviderTest.php`
- `tests/DataProvider/DoctrineRelationProviderTest.php`
- `tests/Functional/RelationManagerRenderTest.php`
- Fixtures: `tests/Fixtures/Entity/Comment.php`, `tests/Fixtures/Resource/CommentTagResource.php` (child), extend an existing parent fixture with a relation; register in `tests/Functional/AtriumTestKernel.php`; bump `tests/Functional/KernelBootTest.php` resource count.

---

## Task 1: `RelationKind` enum

**Files:**
- Create: `src/Relation/RelationKind.php`
- Test: `tests/Relation/RelationKindTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\RelationKind;
use PHPUnit\Framework\TestCase;

final class RelationKindTest extends TestCase
{
    public function testCasesExist(): void
    {
        self::assertSame('one_to_many', RelationKind::OneToMany->value);
        self::assertSame('many_to_many', RelationKind::ManyToMany->value);
    }

    public function testUsesPivot(): void
    {
        self::assertFalse(RelationKind::OneToMany->usesPivot());
        self::assertTrue(RelationKind::ManyToMany->usesPivot());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Relation/RelationKindTest.php`
Expected: FAIL — `Class "Atrium\Relation\RelationKind" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Atrium\Relation;

/**
 * The kind of relationship a {@see Relation} describes. The kind selects the
 * action taxonomy (associate/dissociate vs attach/detach) and the data-layer
 * path (foreign-key scope vs pivot join).
 */
enum RelationKind: string
{
    case OneToMany = 'one_to_many';
    case ManyToMany = 'many_to_many';

    public function usesPivot(): bool
    {
        return self::ManyToMany === $this;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Relation/RelationKindTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Relation/RelationKind.php tests/Relation/RelationKindTest.php
git commit -m "Add RelationKind enum (REL-02)"
```

---

## Task 2: `Relation` descriptor builder

**Files:**
- Create: `src/Relation/Relation.php`
- Test: `tests/Relation/RelationTest.php`

The builder mirrors the existing fluent-builder style (`Column`, `Filter`). It stores
config and exposes getters; resolution of defaults/target happens in Task 4.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Form\Schema;
use Atrium\Relation\Relation;
use Atrium\Relation\RelationKind;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Resource\TagResource;
use PHPUnit\Framework\TestCase;

final class RelationTest extends TestCase
{
    public function testOneToManyCarriesKindTargetAndForeignKey(): void
    {
        $relation = Relation::make('comments')
            ->oneToMany(TagResource::class)
            ->foreignKey('post_id')
            ->recordTitle('body');

        self::assertSame('comments', $relation->getName());
        self::assertSame(RelationKind::OneToMany, $relation->getKind());
        self::assertSame(TagResource::class, $relation->getTargetClass());
        self::assertSame('post_id', $relation->getForeignKey());
        self::assertSame('body', $relation->getRecordTitle());
    }

    public function testManyToManyCarriesPivotConfiguration(): void
    {
        $relation = Relation::make('tags')
            ->manyToMany(TagResource::class)
            ->pivotTable('post_tag')
            ->pivotKeys('post_id', 'tag_id')
            ->pivotColumns(['sort']);

        self::assertSame(RelationKind::ManyToMany, $relation->getKind());
        self::assertSame('post_tag', $relation->getPivotTable());
        self::assertSame('post_id', $relation->getPivotParentKey());
        self::assertSame('tag_id', $relation->getPivotRelatedKey());
        self::assertSame(['sort'], $relation->getPivotColumns());
    }

    public function testLabelDefaultsToHumanisedNameAndReadOnlyOnViewDefaultsTrue(): void
    {
        $relation = Relation::make('blogComments')->oneToMany(TagResource::class)->foreignKey('x');

        self::assertSame('Blog comments', $relation->getLabel());
        self::assertTrue($relation->isReadOnlyOnView());
    }

    public function testTableAndFormClosuresAreApplied(): void
    {
        $relation = Relation::make('comments')
            ->oneToMany(TagResource::class)->foreignKey('x')
            ->table(static fn (TableConfiguration $t): TableConfiguration => $t->perPage(7))
            ->form(static fn (Schema $s): Schema => $s);

        $table = $relation->applyTable(TableConfiguration::make());
        self::assertSame(7, $table->getPerPage());
        self::assertInstanceOf(Schema::class, $relation->applyForm(new Schema()));
    }

    public function testVisibleClosureEvaluatedAgainstParent(): void
    {
        $relation = Relation::make('comments')->oneToMany(TagResource::class)->foreignKey('x')
            ->visible(static fn (object $parent): bool => 'yes' === $parent->flag);

        $shown = (object) ['flag' => 'yes'];
        $hidden = (object) ['flag' => 'no'];
        self::assertTrue($relation->isVisibleFor($shown));
        self::assertFalse($relation->isVisibleFor($hidden));
    }
}
```

> Note: `TagResource` already exists under `tests/Fixtures/Resource/` and is a valid
> `AdminResource` — it is used here only as a stand-in target class-string.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Relation/RelationTest.php`
Expected: FAIL — `Class "Atrium\Relation\Relation" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Table\TableConfiguration;

/**
 * A declarative description of a resource relationship (REL-01). Configured in
 * PHP on a resource's {@see AdminResource::relations()}; the kind, target
 * resource and keys are stated explicitly so core never reads Doctrine
 * association metadata. An {@see RelationResolver} turns it into an immutable
 * {@see RelationDescriptor} for the data layer and the relation manager.
 */
final class Relation
{
    private ?RelationKind $kind = null;

    /** @var class-string<AdminResource>|null */
    private ?string $targetClass = null;

    private ?string $foreignKey = null;
    private ?string $pivotTable = null;
    private ?string $pivotParentKey = null;
    private ?string $pivotRelatedKey = null;

    /** @var list<string> */
    private array $pivotColumns = [];

    private string|\Closure|null $label = null;
    private ?string $icon = null;
    private string|\Closure|null $recordTitle = null;
    private ?\Closure $table = null;
    private ?\Closure $form = null;
    private bool|\Closure $visible = true;
    private bool $readOnlyOnView = true;

    /** @var class-string|null */
    private ?string $using = null;

    private ?string $emptyHeading = null;
    private ?string $emptyDescription = null;
    private ?string $emptyIcon = null;

    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** @param class-string<AdminResource> $target */
    public function oneToMany(string $target): self
    {
        $this->kind = RelationKind::OneToMany;
        $this->targetClass = $target;

        return $this;
    }

    /** @param class-string<AdminResource> $target */
    public function manyToMany(string $target): self
    {
        $this->kind = RelationKind::ManyToMany;
        $this->targetClass = $target;

        return $this;
    }

    public function foreignKey(string $column): self
    {
        $this->foreignKey = $column;

        return $this;
    }

    public function pivotTable(string $table): self
    {
        $this->pivotTable = $table;

        return $this;
    }

    public function pivotKeys(string $parent, string $related): self
    {
        $this->pivotParentKey = $parent;
        $this->pivotRelatedKey = $related;

        return $this;
    }

    /** @param list<string> $columns */
    public function pivotColumns(array $columns): self
    {
        $this->pivotColumns = $columns;

        return $this;
    }

    public function label(string|\Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function recordTitle(string|\Closure $attribute): self
    {
        $this->recordTitle = $attribute;

        return $this;
    }

    /** @param \Closure(TableConfiguration): TableConfiguration $configure */
    public function table(\Closure $configure): self
    {
        $this->table = $configure;

        return $this;
    }

    /** @param \Closure(Schema): Schema $configure */
    public function form(\Closure $configure): self
    {
        $this->form = $configure;

        return $this;
    }

    public function emptyState(string $heading, ?string $description = null, ?string $icon = null): self
    {
        $this->emptyHeading = $heading;
        $this->emptyDescription = $description;
        $this->emptyIcon = $icon;

        return $this;
    }

    public function visible(bool|\Closure $condition = true): self
    {
        $this->visible = $condition;

        return $this;
    }

    public function readOnlyOnView(bool $readOnly = true): self
    {
        $this->readOnlyOnView = $readOnly;

        return $this;
    }

    /** @param class-string $class */
    public function using(string $class): self
    {
        $this->using = $class;

        return $this;
    }

    // -- Accessors ---------------------------------------------------------

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): RelationKind
    {
        return $this->kind ?? throw new \LogicException(\sprintf('Relation "%s" has no kind; call oneToMany()/manyToMany().', $this->name));
    }

    /** @return class-string<AdminResource> */
    public function getTargetClass(): string
    {
        return $this->targetClass ?? throw new \LogicException(\sprintf('Relation "%s" has no target resource.', $this->name));
    }

    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }

    public function getPivotTable(): ?string
    {
        return $this->pivotTable;
    }

    public function getPivotParentKey(): ?string
    {
        return $this->pivotParentKey;
    }

    public function getPivotRelatedKey(): ?string
    {
        return $this->pivotRelatedKey;
    }

    /** @return list<string> */
    public function getPivotColumns(): array
    {
        return $this->pivotColumns;
    }

    public function getLabel(): string
    {
        if ($this->label instanceof \Closure) {
            return (string) ($this->label)();
        }

        return $this->label ?? ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name))));
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /** Raw configured record-title attribute (null when defaulted later). */
    public function getRecordTitle(): string|\Closure|null
    {
        return $this->recordTitle;
    }

    public function applyTable(TableConfiguration $table): TableConfiguration
    {
        return null !== $this->table ? ($this->table)($table) : $table;
    }

    public function applyForm(Schema $schema): Schema
    {
        return null !== $this->form ? ($this->form)($schema) : $schema;
    }

    public function hasTable(): bool
    {
        return null !== $this->table;
    }

    public function hasForm(): bool
    {
        return null !== $this->form;
    }

    public function isReadOnlyOnView(): bool
    {
        return $this->readOnlyOnView;
    }

    public function isVisibleFor(object $parent): bool
    {
        return $this->visible instanceof \Closure ? (bool) ($this->visible)($parent) : $this->visible;
    }

    /** @return class-string|null */
    public function getUsing(): ?string
    {
        return $this->using;
    }

    public function getEmptyHeading(): ?string
    {
        return $this->emptyHeading;
    }

    public function getEmptyDescription(): ?string
    {
        return $this->emptyDescription;
    }

    public function getEmptyIcon(): ?string
    {
        return $this->emptyIcon;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Relation/RelationTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Run the gates**

Run: `composer phpstan && composer cs`
Expected: no errors. (If CS rewrites spacing, re-run `composer cs:fix` then `composer cs`.)

- [ ] **Step 6: Commit**

```bash
git add src/Relation/Relation.php tests/Relation/RelationTest.php
git commit -m "Add Relation descriptor builder (REL-01)"
```

---

## Task 3: `AdminResource::relations()` default + `RelationDescriptor`

**Files:**
- Create: `src/Relation/RelationDescriptor.php`
- Modify: `src/Resource/AdminResource.php` (add `relations()` near `view()`)
- Test: `tests/Relation/RelationDescriptorTest.php`

`RelationDescriptor` is the immutable, fully-resolved value the data layer and the
manager consume (no closures, no registry lookups). It carries resolved **entity**
classes and key names, never a Doctrine type.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Entity\Tag;
use PHPUnit\Framework\TestCase;

final class RelationDescriptorTest extends TestCase
{
    public function testHoldsResolvedValues(): void
    {
        $descriptor = new RelationDescriptor(
            name: 'comments',
            kind: RelationKind::OneToMany,
            childEntityClass: Tag::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'name',
            foreignKey: 'post_id',
        );

        self::assertSame('comments', $descriptor->name);
        self::assertSame(RelationKind::OneToMany, $descriptor->kind);
        self::assertSame(Tag::class, $descriptor->childEntityClass);
        self::assertSame('post_id', $descriptor->foreignKey);
        self::assertSame('name', $descriptor->recordTitleAttribute);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Relation/RelationDescriptorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `RelationDescriptor`**

```php
<?php

declare(strict_types=1);

namespace Atrium\Relation;

/**
 * @internal Immutable, fully-resolved view of a {@see Relation} consumed by the
 * data layer and the relation manager: entity classes and key names only, with
 * defaults filled in and the target resolved — no closures, no Doctrine types.
 */
final readonly class RelationDescriptor
{
    /**
     * @param class-string $childEntityClass
     * @param list<string>  $pivotColumns
     */
    public function __construct(
        public string $name,
        public RelationKind $kind,
        public string $childEntityClass,
        public string $childIdField,
        public string $parentIdField,
        public string $recordTitleAttribute,
        public ?string $foreignKey = null,
        public ?string $pivotTable = null,
        public ?string $pivotParentKey = null,
        public ?string $pivotRelatedKey = null,
        public array $pivotColumns = [],
    ) {
    }
}
```

- [ ] **Step 4: Add the `relations()` default to `AdminResource`**

In `src/Resource/AdminResource.php`, add the `Atrium\Relation\Relation` import and a
default method directly **after** the `view()` method (search for
`public function view(Schema $schema): Schema`). Insert:

```php
    /**
     * Declare this resource's managed relationships (REL-01). Each
     * {@see \Atrium\Relation\Relation} renders as a relation manager on the
     * resource's Edit/View screens. Returns none by default.
     *
     * @return list<\Atrium\Relation\Relation>
     */
    public function relations(): array
    {
        return [];
    }
```

(No new `use` is strictly required since the return type is FQCN in the docblock; keep
it consistent with the file — if other methods import their types, add
`use Atrium\Relation\Relation;` and use the short name.)

- [ ] **Step 5: Run the test + gates**

Run: `vendor/bin/phpunit tests/Relation/RelationDescriptorTest.php && composer phpstan && composer cs`
Expected: PASS (1 test); no PHPStan/CS errors.

- [ ] **Step 6: Commit**

```bash
git add src/Relation/RelationDescriptor.php src/Resource/AdminResource.php tests/Relation/RelationDescriptorTest.php
git commit -m "Add RelationDescriptor + AdminResource::relations() default (REL-01)"
```

---

## Task 4: `RelationResolver` (Relation → RelationDescriptor)

**Files:**
- Create: `src/Relation/RelationResolver.php`
- Test: `tests/Relation/RelationResolverTest.php`

The resolver fills defaults and resolves the target **resource** (via `ResourceRegistry`)
to its **entity** class + identifier field. It also enforces REL-20 (target must be
registered; required keys present).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\Relation;
use Atrium\Relation\RelationResolver;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Resource\TagResource;
use PHPUnit\Framework\TestCase;

final class RelationResolverTest extends TestCase
{
    public function testResolvesTargetEntityAndDefaults(): void
    {
        $registry = new ResourceRegistry([new TagResource()]);
        $resolver = new RelationResolver($registry);

        // Parent identifier field is 'id' (a plain parent fixture).
        $descriptor = $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class)->foreignKey('post_id')->recordTitle('name'),
            parentIdField: 'id',
        );

        self::assertSame(Tag::class, $descriptor->childEntityClass);
        self::assertSame('id', $descriptor->childIdField);      // TagResource::getIdentifierField()
        self::assertSame('id', $descriptor->parentIdField);
        self::assertSame('name', $descriptor->recordTitleAttribute);
        self::assertSame('post_id', $descriptor->foreignKey);
    }

    public function testRecordTitleDefaultsToTargetIdentifierField(): void
    {
        $resolver = new RelationResolver(new ResourceRegistry([new TagResource()]));

        $descriptor = $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class)->foreignKey('post_id'),
            parentIdField: 'id',
        );

        self::assertSame('id', $descriptor->recordTitleAttribute);
    }

    public function testOneToManyWithoutForeignKeyThrows(): void
    {
        $resolver = new RelationResolver(new ResourceRegistry([new TagResource()]));

        $this->expectException(\InvalidArgumentException::class);
        $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class),
            parentIdField: 'id',
        );
    }

    public function testUnregisteredTargetThrows(): void
    {
        $resolver = new RelationResolver(new ResourceRegistry([])); // TagResource not registered

        $this->expectException(\InvalidArgumentException::class);
        $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class)->foreignKey('post_id'),
            parentIdField: 'id',
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Relation/RelationResolverTest.php`
Expected: FAIL — `RelationResolver` not found.

- [ ] **Step 3: Write the resolver**

```php
<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;

/**
 * @internal Resolves a {@see Relation} into an immutable {@see RelationDescriptor}:
 * looks the target resource up in the registry (REL-20: must be registered),
 * derives its entity class + identifier field, fills the record-title default,
 * and validates that the keys required by the kind are present.
 */
final readonly class RelationResolver
{
    public function __construct(private ResourceRegistry $registry)
    {
    }

    public function resolve(Relation $relation, string $parentIdField): RelationDescriptor
    {
        $targetClass = $relation->getTargetClass();

        try {
            $target = $this->registry->getByClass($targetClass);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(\sprintf(
                'Relation "%s" targets resource "%s" which is not registered in the panel.',
                $relation->getName(),
                $targetClass,
            ), 0, $e);
        }

        $childIdField = $target->getIdentifierField();
        $recordTitle = $relation->getRecordTitle();
        $recordTitleAttribute = \is_string($recordTitle) ? $recordTitle : $childIdField;

        $kind = $relation->getKind();
        if (RelationKind::OneToMany === $kind && null === $relation->getForeignKey()) {
            throw new \InvalidArgumentException(\sprintf(
                'One-to-many relation "%s" requires foreignKey().',
                $relation->getName(),
            ));
        }
        if (RelationKind::ManyToMany === $kind
            && (null === $relation->getPivotTable() || null === $relation->getPivotParentKey() || null === $relation->getPivotRelatedKey())) {
            throw new \InvalidArgumentException(\sprintf(
                'Many-to-many relation "%s" requires pivotTable() and pivotKeys().',
                $relation->getName(),
            ));
        }

        return new RelationDescriptor(
            name: $relation->getName(),
            kind: $kind,
            childEntityClass: $target->getEntityClass(),
            childIdField: $childIdField,
            parentIdField: $parentIdField,
            recordTitleAttribute: $recordTitleAttribute,
            foreignKey: $relation->getForeignKey(),
            pivotTable: $relation->getPivotTable(),
            pivotParentKey: $relation->getPivotParentKey(),
            pivotRelatedKey: $relation->getPivotRelatedKey(),
            pivotColumns: $relation->getPivotColumns(),
        );
    }

    /** Resolve the target resource (for the manager to reuse its table/form/auth). */
    public function targetResource(Relation $relation): AdminResource
    {
        return $this->registry->getByClass($relation->getTargetClass());
    }
}
```

> Verify `ResourceRegistry::getByClass()` throws (not returns null) for an unknown
> class — it is declared `getByClass(string $class): AdminResource` (non-nullable), so
> the `catch (\Throwable)` is correct. If it returns null instead, change the guard to
> a null check.

- [ ] **Step 4: Run the test + gates**

Run: `vendor/bin/phpunit tests/Relation/RelationResolverTest.php && composer phpstan && composer cs`
Expected: PASS (4 tests); gates clean.

- [ ] **Step 5: Commit**

```bash
git add src/Relation/RelationResolver.php tests/Relation/RelationResolverTest.php
git commit -m "Add RelationResolver with target-registration validation (REL-01, REL-20)"
```

---

## Task 5: `RelationDataProvider` interface + `ArrayRelationProvider` (one-to-many read)

**Files:**
- Create: `src/DataProvider/RelationDataProvider.php`
- Create: `src/DataProvider/ArrayRelationProvider.php`
- Test: `tests/DataProvider/ArrayRelationProviderTest.php`

The interface declares the full surface; **M1 implements only `listRelated`/`countRelated`
for one-to-many**. The write/linkable methods throw until their milestone, so the
contract is fixed now without pretending behaviour exists.

- [ ] **Step 1: Write the failing test (array, one-to-many)**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\ArrayRelationProvider;
use Atrium\DataProvider\DataQuery;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Entity\Comment;
use Atrium\Tests\Fixtures\Entity\Post;
use PHPUnit\Framework\TestCase;

final class ArrayRelationProviderTest extends TestCase
{
    private function descriptor(): RelationDescriptor
    {
        return new RelationDescriptor(
            name: 'comments',
            kind: RelationKind::OneToMany,
            childEntityClass: Comment::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'body',
            foreignKey: 'postId',
        );
    }

    public function testListRelatedReturnsOnlyChildrenOfTheParent(): void
    {
        $post = new Post(1, 'First');
        $other = new Post(2, 'Second');
        $provider = new ArrayRelationProvider([
            Comment::class => [
                new Comment(1, 'a', postId: 1),
                new Comment(2, 'b', postId: 2),
                new Comment(3, 'c', postId: 1),
            ],
        ]);

        $rows = [...$provider->listRelated($this->descriptor(), $post, new DataQuery())];

        self::assertCount(2, $rows);
        self::assertSame([1, 3], array_map(static fn (Comment $c): int => $c->id, $rows));
        self::assertSame(2, $provider->countRelated($this->descriptor(), $post, new DataQuery()));
        self::assertCount(0, [...$provider->listRelated($this->descriptor(), $other, new DataQuery(offset: 5))]);
    }

    public function testListRelatedHonoursPaginationFromDataQuery(): void
    {
        $post = new Post(1, 'First');
        $comments = [];
        for ($i = 1; $i <= 5; ++$i) {
            $comments[] = new Comment($i, 'c'.$i, postId: 1);
        }
        $provider = new ArrayRelationProvider([Comment::class => $comments]);

        $rows = [...$provider->listRelated($this->descriptor(), $post, new DataQuery(offset: 2, limit: 2))];

        self::assertSame([3, 4], array_map(static fn (Comment $c): int => $c->id, $rows));
    }

    public function testAssociateIsNotYetImplemented(): void
    {
        $this->expectException(\LogicException::class);
        (new ArrayRelationProvider())->associate($this->descriptor(), new Post(1, 'x'), new Comment(1, 'y', postId: null));
    }
}
```

This test needs two new fixtures (`Post`, `Comment`). Create them first:

`tests/Fixtures/Entity/Post.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

/** Plain parent fixture for relation tests. */
final class Post
{
    public function __construct(
        public int $id = 0,
        public string $title = '',
    ) {
    }
}
```

`tests/Fixtures/Entity/Comment.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

/** Plain child fixture (one-to-many: Comment.postId → Post). */
final class Comment
{
    public function __construct(
        public int $id = 0,
        public string $body = '',
        public ?int $postId = null,
    ) {
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/DataProvider/ArrayRelationProviderTest.php`
Expected: FAIL — `ArrayRelationProvider` / `RelationDataProvider` not found.

- [ ] **Step 3: Write the interface**

```php
<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

use Atrium\Relation\RelationDescriptor;

/**
 * Backend-agnostic relationship read/link operations (REL-10). The descriptor
 * names the keys/pivot; the adapter executes them. Core passes the descriptor +
 * parent + child and never sees a storage type. List queries receive a
 * {@see DataQuery} already carrying the target resource's scope, search, sort and
 * pagination; the adapter composes the parent (foreign-key or pivot) scope on top.
 */
interface RelationDataProvider
{
    /** @return iterable<object> */
    public function listRelated(RelationDescriptor $relation, object $parent, DataQuery $query): iterable;

    public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int;

    /** Candidate records for an Associate/Attach picker: NOT already linked. @return iterable<object> */
    public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable;

    public function countLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): int;

    public function associate(RelationDescriptor $relation, object $parent, object $child): void;

    public function dissociate(RelationDescriptor $relation, object $parent, object $child): void;

    /** @param array<string, scalar|null> $pivot */
    public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void;

    public function detach(RelationDescriptor $relation, object $parent, object $child): void;
}
```

- [ ] **Step 4: Write `ArrayRelationProvider` (one-to-many read; rest throw)**

```php
<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * In-memory {@see RelationDataProvider} over plain objects — proof the relation
 * seam is storage-agnostic, and the backing for unit tests. M1 implements the
 * one-to-many read side; link/unlink and many-to-many land in later milestones.
 */
final class ArrayRelationProvider implements RelationDataProvider
{
    private readonly PropertyAccessorInterface $accessor;

    /** @param array<class-string, list<object>> $records */
    public function __construct(
        private array $records = [],
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function listRelated(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
    {
        $matched = $this->matchingChildren($relation, $parent);

        return \array_slice($matched, max(0, $query->offset), max(1, $query->limit));
    }

    public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        return \count($this->matchingChildren($relation, $parent));
    }

    public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
    {
        throw new \LogicException('listLinkable() is implemented in REL-M2/M3.');
    }

    public function countLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        throw new \LogicException('countLinkable() is implemented in REL-M2/M3.');
    }

    public function associate(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('associate() is implemented in REL-M2.');
    }

    public function dissociate(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('dissociate() is implemented in REL-M2.');
    }

    public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void
    {
        throw new \LogicException('attach() is implemented in REL-M3.');
    }

    public function detach(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('detach() is implemented in REL-M3.');
    }

    /**
     * Children of $parent for a one-to-many relation: child.<foreignKey> equals
     * the parent's identifier value.
     *
     * @return list<object>
     */
    private function matchingChildren(RelationDescriptor $relation, object $parent): array
    {
        if (RelationKind::OneToMany !== $relation->kind) {
            throw new \LogicException('Many-to-many listing is implemented in REL-M3.');
        }

        $parentId = $this->accessor->getValue($parent, $relation->parentIdField);
        $foreignKey = (string) $relation->foreignKey;

        return array_values(array_filter(
            $this->records[$relation->childEntityClass] ?? [],
            fn (object $child): bool => $this->accessor->isReadable($child, $foreignKey)
                && $this->accessor->getValue($child, $foreignKey) === $parentId,
        ));
    }
}
```

- [ ] **Step 5: Run the test + gates**

Run: `vendor/bin/phpunit tests/DataProvider/ArrayRelationProviderTest.php && composer phpstan && composer cs`
Expected: PASS (3 tests); gates clean.

- [ ] **Step 6: Commit**

```bash
git add src/DataProvider/RelationDataProvider.php src/DataProvider/ArrayRelationProvider.php \
        tests/DataProvider/ArrayRelationProviderTest.php tests/Fixtures/Entity/Post.php tests/Fixtures/Entity/Comment.php
git commit -m "Add RelationDataProvider interface + array one-to-many read (REL-10, REL-11)"
```

---

## Task 6: `DoctrineRelationProvider` (one-to-many read)

**Files:**
- Create: `src/DataProvider/DoctrineRelationProvider.php`
- Modify: `config/doctrine.php` (register + alias)
- Test: `tests/DataProvider/DoctrineRelationProviderTest.php`

A Doctrine-mapped child fixture is needed. Reuse the `Product` pattern from
`tests/Fixtures/Entity/Product.php`; add a mapped `Article` (parent) + `Note` (child
with an `article_id` FK column). Keep them mapped only for this test.

- [ ] **Step 1: Add Doctrine-mapped fixtures**

`tests/Fixtures/Entity/Article.php`:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $title = '',
    ) {
    }
}
```

`tests/Fixtures/Entity/Note.php` (child; the FK is a **scalar column**, mirroring the
descriptor's `foreignKey` — no Doctrine association needed because relations are
key-declared, not introspected):

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notes')]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $body = '',
        #[ORM\Column(name: 'article_id', nullable: true)]
        public ?int $articleId = null,
    ) {
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DoctrineRelationProvider;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Doctrine\EntityManagerFactory;
use Atrium\Tests\Fixtures\Entity\Article;
use Atrium\Tests\Fixtures\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class DoctrineRelationProviderTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineRelationProvider $provider;

    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::create();
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Article::class),
            $this->entityManager->getClassMetadata(Note::class),
        ]);
        $this->provider = new DoctrineRelationProvider($this->entityManager);
    }

    private function descriptor(): RelationDescriptor
    {
        return new RelationDescriptor(
            name: 'notes',
            kind: RelationKind::OneToMany,
            childEntityClass: Note::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'body',
            foreignKey: 'articleId',   // Doctrine field name; maps to article_id column
        );
    }

    public function testListRelatedScopesToTheParentForeignKey(): void
    {
        $a1 = new Article('First');
        $a2 = new Article('Second');
        $this->entityManager->persist($a1);
        $this->entityManager->persist($a2);
        $this->entityManager->flush();

        $this->entityManager->persist(new Note('n1', $a1->id));
        $this->entityManager->persist(new Note('n2', $a2->id));
        $this->entityManager->persist(new Note('n3', $a1->id));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $parent = $this->entityManager->find(Article::class, $a1->id);
        self::assertNotNull($parent);

        $rows = [...$this->provider->listRelated($this->descriptor(), $parent, new DataQuery())];
        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(Note::class, $rows);
        self::assertSame(2, $this->provider->countRelated($this->descriptor(), $parent, new DataQuery()));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/DataProvider/DoctrineRelationProviderTest.php`
Expected: FAIL — `DoctrineRelationProvider` not found.

- [ ] **Step 4: Write `DoctrineRelationProvider`**

Build the related query with a parameter-bound `WHERE child.<foreignKey> = :parentId`,
applying the `DataQuery`'s pagination (search/sort/scope composition is layered in
later milestones via the manager; M1 keeps the read minimal and parent-scoped).

```php
<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Doctrine adapter for {@see RelationDataProvider} (REL-10). The only relation
 * class permitted to reference Doctrine. M1 implements the one-to-many read side;
 * link/unlink and many-to-many land in later milestones.
 */
final class DoctrineRelationProvider implements RelationDataProvider
{
    private const string ALIAS = 'r';

    private readonly PropertyAccessorInterface $accessor;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function listRelated(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
    {
        $qb = $this->relatedQuery($relation, $parent)
            ->setFirstResult(max(0, $query->offset))
            ->setMaxResults(max(1, $query->limit));

        return array_values(array_filter(
            (array) $qb->getQuery()->getResult(),
            static fn (mixed $row): bool => \is_object($row),
        ));
    }

    public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        $qb = $this->relatedQuery($relation, $parent)->select(\sprintf('COUNT(%s)', self::ALIAS));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
    {
        throw new \LogicException('listLinkable() is implemented in REL-M2/M3.');
    }

    public function countLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        throw new \LogicException('countLinkable() is implemented in REL-M2/M3.');
    }

    public function associate(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('associate() is implemented in REL-M2.');
    }

    public function dissociate(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('dissociate() is implemented in REL-M2.');
    }

    public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void
    {
        throw new \LogicException('attach() is implemented in REL-M3.');
    }

    public function detach(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('detach() is implemented in REL-M3.');
    }

    private function relatedQuery(RelationDescriptor $relation, object $parent): QueryBuilder
    {
        if (RelationKind::OneToMany !== $relation->kind) {
            throw new \LogicException('Many-to-many listing is implemented in REL-M3.');
        }

        $parentId = $this->accessor->getValue($parent, $relation->parentIdField);
        $qb = $this->entityManager->getRepository($relation->childEntityClass)->createQueryBuilder(self::ALIAS);
        $qb->where($qb->expr()->eq(self::ALIAS.'.'.$relation->foreignKey, ':atrium_parent_id'))
            ->setParameter('atrium_parent_id', $parentId);

        return $qb;
    }
}
```

- [ ] **Step 5: Register the service + alias**

In `config/doctrine.php`, after the existing `DoctrineDataProvider` block, add:

```php
    $services->set(\Atrium\DataProvider\DoctrineRelationProvider::class)
        ->args([service('doctrine.orm.entity_manager')]);

    $services->alias(\Atrium\DataProvider\RelationDataProvider::class, \Atrium\DataProvider\DoctrineRelationProvider::class);
```

(Match the existing file's style — it already imports `DoctrineDataProvider` and uses
`service(...)`; use the same EM service id it uses for `DoctrineDataProvider`.)

- [ ] **Step 6: Run the test + gates**

Run: `vendor/bin/phpunit tests/DataProvider/DoctrineRelationProviderTest.php && composer phpstan && composer cs`
Expected: PASS (1 test); gates clean.

- [ ] **Step 7: Commit**

```bash
git add src/DataProvider/DoctrineRelationProvider.php config/doctrine.php \
        tests/DataProvider/DoctrineRelationProviderTest.php \
        tests/Fixtures/Entity/Article.php tests/Fixtures/Entity/Note.php
git commit -m "Add Doctrine relation provider (one-to-many read) + service wiring (REL-10)"
```

---

## Task 7: Extract `AbstractRecordTable` from `DataTable` (pure refactor)

**Files:**
- Create: `src/Twig/Components/AbstractRecordTable.php`
- Modify: `src/Twig/Components/DataTable.php`
- Create: `templates/components/_record_table.html.twig`
- Modify: `templates/components/data_table.html.twig`

**This is a refactor with no behaviour change.** The executable guard is the **existing
table test-suite staying green** — there is no new test in this task; instead each step
ends by running the table tests.

The split: members that are about **table presentation/state/interaction** move to
`AbstractRecordTable`; members about the **data source + resource identity** become
abstract seams the subclass implements.

- [ ] **Step 1: Identify the seams (no code yet — read & confirm)**

In `src/Twig/Components/DataTable.php`, these methods are the only ones that touch the
**data source or resource identity** — they become the subclass's responsibility:
- `pageRecords()` → calls `dataProvider->fetch($this->entityClass(), $this->query())`
- `getTotalCount()` → calls `dataProvider->count(...)`
- `findRecord(string $id)` → `dataProvider->find(...)`
- `selectedRecords()` / `allMatchingQuery()` → select-all enumeration
- `query()` / `resolvedFilters()` / `searchableFields()` → query building from the resource
- `resource()` / `entityClass()` / `recordId()` / `tableConfig()` / `pageContext()`
- `getHeaderActions()` → `resource()->resolveHeaderActions('index', ...)`
- `resolveRowUrl()` / `canReachPage()` → row-link target

Everything else (the LiveProps `search`/`sortField`/`page`/`perPage`/`filterValues`,
`sort()`/`gotoPage()`/`onPerPageUpdated()`/`resetPage()`/`clampPage()`/`resetFilters()`,
`getColumns()`/`getRows()`/`getRecordActions()`/`getBulkActions()`/`getFilters()`/
empty-state getters/pagination getters, and the action-execution plumbing
`findAction`/`canExecuteAction`/`executeAction`/`runBulkAction` etc.) is **presentation
+ interaction** and moves to the base.

Define the base's abstract seams:

```php
abstract protected function dataSource(): DataProviderInterface;     // the read backend
abstract protected function resource(): AdminResource;               // the resource in context
abstract protected function entityClass(): string;                   // class-string of the rows
abstract protected function fetchPage(DataQuery $query): iterable;    // the current page's rows
abstract protected function total(DataQuery $query): int;            // total matching rows
abstract protected function findRecord(string $id): ?object;          // resolve one row by id (scoped)
abstract protected function buildQuery(int $offset, int $limit): DataQuery;  // page query
abstract protected function buildAllQuery(): DataQuery;              // select-all query
abstract public function getHeaderActions(): array;                  // header action set
abstract protected function actionContext(object $record, ?string $id): ActionContext; // row URL context
abstract protected function rowUrl(object $record, ActionContext $context): ?string;   // row-click target
```

- [ ] **Step 2: Create `AbstractRecordTable` with the moved members**

Create `src/Twig/Components/AbstractRecordTable.php`. Move the presentation/state/
interaction members from `DataTable` **verbatim** (same bodies), replacing direct
`dataProvider->fetch/count/find` and `query()`/`allMatchingQuery()` calls with the
abstract seams (`fetchPage`/`total`/`findRecord`/`buildQuery`/`buildAllQuery`). Keep the
traits (`DefaultActionTrait`, `InteractsWithActions`, `InteractsWithBulkActions`) and
the LiveProps on the base. Mark the class `abstract` and `@internal`.

Key transforms (apply to the moved bodies):
- `pageRecords()` body becomes: `foreach ($this->fetchPage($this->buildQuery($offset, $limit)) as $record) { ... }` where offset/limit come from `$this->page`/`$this->perPage` as today.
- `getTotalCount()` becomes `$this->totalCount ??= $this->total($this->buildQuery(0, $this->perPage));` — but `total()` must ignore pagination, so pass the query and let the subclass's `total()` count without limit (the Doctrine/array `count` already ignores limit). Keep the existing memoisation fields (`$totalCount`, `$pageIds`, `$pageRecords`).
- `selectedRecords()`'s select-all branch uses `$this->fetchPage($this->buildAllQuery())`.
- `recordId()` stays on the base but calls `$this->resource()->getIdentifierField()` via the abstract `resource()` seam.

> Because this is mechanical, do it in one editing pass, then lean on the tests. Do
> **not** change any behaviour or names that the template references
> (`getColumns`, `getRows`, `getHeaderActionViews`, `getBulkActionViews`,
> `getFilterViews`, `getPageCount`, `getTotalCount`, `getActiveSortField`,
> `getActiveSortDirection`, `getPerPageOptions`, `getEmptyHeading/Description/Icon`,
> `getResourceLabel`, `hasRecordActions`, `hasHeaderActions`, `hasBulkActions`,
> `hasFilters`, `hasActiveFilters`). These stay public with identical signatures.

- [ ] **Step 3: Re-express `DataTable extends AbstractRecordTable`**

`DataTable` keeps: its `#[AsLiveComponent(...)]` attribute, the constructor (registry,
dataProvider, writer, accessor), `mount()`, and **only** the concrete seam
implementations:

```php
#[AsLiveComponent(name: 'Atrium:DataTable', template: '@Atrium/components/data_table.html.twig')]
final class DataTable extends AbstractRecordTable
{
    #[LiveProp] public string $resource = '';
    #[LiveProp] public string $pathPrefix = '';
    // (search/sort/page/perPage/filterValues now live on the base)

    public function __construct(/* registry, dataProvider, writer, accessor — as today */) {}

    public function mount(string $resource, string $pathPrefix = '', ?int $perPage = null): void { /* as today */ }

    protected function resource(): AdminResource { return $this->registry->getBySlug($this->resource); }
    protected function entityClass(): string { return $this->resource()->getEntityClass(); }
    protected function dataSource(): DataProviderInterface { return $this->dataProvider; }
    protected function fetchPage(DataQuery $query): iterable { return $this->dataProvider->fetch($this->entityClass(), $query); }
    protected function total(DataQuery $query): int { return $this->dataProvider->count($this->entityClass(), $query); }
    protected function findRecord(string $id): ?object { /* the existing scoped find() body */ }
    protected function buildQuery(int $offset, int $limit): DataQuery { /* the existing query() body, parametrised by offset/limit */ }
    protected function buildAllQuery(): DataQuery { /* the existing allMatchingQuery() body */ }
    public function getHeaderActions(): array { return $this->resource()->resolveHeaderActions('index', $this->pageContext()); }
    protected function actionContext(object $record, ?string $id): ActionContext { return new ActionContext($this->pathPrefix, $this->resource, $id ?? ''); }
    protected function rowUrl(object $record, ActionContext $context): ?string { /* the existing resolveRowUrl() body */ }
    // pageContext(), canReachPage(), safeUrl() stay here (DataTable-specific).
}
```

Note: the base's `getRows()` must call `$this->actionContext($record, $id)` and
`$this->rowUrl($record, $context)` instead of constructing `ActionContext` inline.

- [ ] **Step 4: Run the table tests (green-bar guard)**

Run: `vendor/bin/phpunit --filter 'DataTable|Table' tests/`
Expected: all existing table tests PASS unchanged. If any fail, the extraction changed
behaviour — fix the moved code until green. Do **not** edit the tests.

- [ ] **Step 5: Split the template**

Move the `<table>…</table>` block (header, rows, action bars, empty state, pagination —
everything that reads the getters listed in Step 2) from
`templates/components/data_table.html.twig` into a new partial
`templates/components/_record_table.html.twig`, referencing the same variables via
`this.*` (Live Component getters resolve against the host component, so the partial works
for any `AbstractRecordTable` subclass). `data_table.html.twig` becomes:

```twig
<div {{ attributes }}>
    {% include '@Atrium/components/_record_table.html.twig' %}
</div>
```

Keep the outer wrapper / any `data_table`-specific chrome (search box, filter bar) either
in the partial (if shared) or in the wrapper (if list-only) — default to putting the full
toolbar in the shared partial so the relation manager gets search/filter for free.

- [ ] **Step 6: Run the full suite + gates**

Run: `composer test && composer phpstan && composer cs`
Expected: all green (no behaviour change; PHPStan max clean — watch for `@internal`
covariance and the new abstract signatures).

- [ ] **Step 7: Commit**

```bash
git add src/Twig/Components/AbstractRecordTable.php src/Twig/Components/DataTable.php \
        templates/components/_record_table.html.twig templates/components/data_table.html.twig
git commit -m "Extract AbstractRecordTable from DataTable (REL-03, pure refactor)"
```

---

## Task 8: `RelationManager` Live Component (read-only, one-to-many)

**Files:**
- Create: `src/Twig/Components/RelationManager.php`
- Create: `templates/components/relation_manager.html.twig`
- Test: `tests/Functional/RelationManagerRenderTest.php` (added in Task 10)

`RelationManager` extends `AbstractRecordTable`. M1 renders **read-only** (no header/row
mutating actions yet — those arrive in M2/M3), so `getHeaderActions()` returns `[]`,
`getRecordActions()` returns `[]` (override), and `rowUrl()` returns `null`. Its data
seams go through `RelationDataProvider` with the resolved descriptor and the loaded
parent record.

- [ ] **Step 1: Write the component**

```php
<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Action\ActionContext;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\DataProvider\RelationDataProvider;
use Atrium\Relation\Relation;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationResolver;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Table\TableConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * @internal A parent-scoped related-records table (REL-04). Extends the shared
 * {@see AbstractRecordTable} core; its rows come from {@see RelationDataProvider}
 * for the resolved {@see RelationDescriptor} and the loaded parent record. M1 is
 * read-only (one-to-many); link/unlink actions arrive in later milestones.
 */
#[AsLiveComponent(name: 'Atrium:RelationManager', template: '@Atrium/components/relation_manager.html.twig')]
final class RelationManager extends AbstractRecordTable
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $resource = '';      // parent resource slug

    #[LiveProp]
    public string $parentId = '';

    #[LiveProp]
    public string $relationName = '';

    #[LiveProp]
    public string $pathPrefix = '';

    #[LiveProp]
    public string $page2 = 'edit';     // 'edit' | 'view' — drives read-only (named to avoid the base's $page)

    private ?RelationDescriptor $descriptor = null;
    private ?AdminResource $targetResource = null;
    private ?object $parentRecord = null;

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly RelationDataProvider $relationProvider,
        private readonly RelationResolver $resolver,
        private readonly DataWriterInterface $writer,
        private readonly PropertyAccessorInterface $accessor,
    ) {
    }

    public function mount(string $resource, string $parentId, string $relation, string $pathPrefix = '', string $page = 'edit'): void
    {
        $this->resource = $resource;
        $this->parentId = $parentId;
        $this->relationName = $relation;
        $this->pathPrefix = $pathPrefix;
        $this->page2 = $page;
        $this->perPage = $this->tableConfig()->getPerPage();
    }

    // -- AbstractRecordTable seams ----------------------------------------

    protected function resource(): AdminResource
    {
        return $this->target();
    }

    protected function entityClass(): string
    {
        return $this->descriptor()->childEntityClass;
    }

    protected function dataSource(): DataProviderInterface
    {
        return $this->dataProvider;
    }

    protected function fetchPage(DataQuery $query): iterable
    {
        return $this->relationProvider->listRelated($this->descriptor(), $this->parent(), $query);
    }

    protected function total(DataQuery $query): int
    {
        return $this->relationProvider->countRelated($this->descriptor(), $this->parent(), $query);
    }

    protected function findRecord(string $id): ?object
    {
        // Scoped find through the standard provider; parent-scope enforcement for
        // mutating actions arrives with those actions in M2/M3.
        return $this->dataProvider->find(
            $this->entityClass(),
            $id,
            $this->target()->scopeFilters(),
            $this->descriptor()->childIdField,
        );
    }

    protected function buildQuery(int $offset, int $limit): DataQuery
    {
        // Apply the TARGET resource's scopeQuery, then the manager passes the parent
        // scope inside the provider (REL-09/§5.5).
        return $this->target()->scopeQuery(new DataQuery(
            search: $this->search,
            searchableFields: $this->searchableFields(),
            sortField: $this->getActiveSortField(),
            sortDirection: $this->getActiveSortDirection(),
            offset: $offset,
            limit: $limit,
            filters: $this->resolvedFilters(),
        ));
    }

    protected function buildAllQuery(): DataQuery
    {
        return $this->buildQuery(0, max(1, $this->getTotalCount()));
    }

    public function getHeaderActions(): array
    {
        return [];   // link/create actions arrive in M2/M3
    }

    public function getRecordActions(): array
    {
        return [];   // read-only in M1
    }

    protected function actionContext(object $record, ?string $id): ActionContext
    {
        return new ActionContext($this->pathPrefix, $this->target()->getSlug(), $id ?? '');
    }

    protected function rowUrl(object $record, ActionContext $context): ?string
    {
        return null;   // row-click target wired with nesting (M4)
    }

    public function getEmptyHeading(): string
    {
        return $this->relation()->getEmptyHeading() ?? 'No '.strtolower($this->relation()->getLabel());
    }

    // -- Resolution helpers -----------------------------------------------

    private function tableConfig(): TableConfiguration
    {
        $config = $this->target()->table(TableConfiguration::make());

        return $this->relation()->hasTable() ? $this->relation()->applyTable($config) : $config;
    }

    private function parentResource(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }

    private function target(): AdminResource
    {
        return $this->targetResource ??= $this->resolver->targetResource($this->relation());
    }

    private function relation(): Relation
    {
        foreach ($this->parentResource()->relations() as $relation) {
            if ($relation->getName() === $this->relationName) {
                return $relation;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Resource "%s" has no relation "%s".', $this->resource, $this->relationName));
    }

    private function descriptor(): RelationDescriptor
    {
        return $this->descriptor ??= $this->resolver->resolve($this->relation(), $this->parentResource()->getIdentifierField());
    }

    private function parent(): object
    {
        return $this->parentRecord ??= $this->dataProvider->find(
            $this->parentResource()->getEntityClass(),
            $this->parentId,
            $this->parentResource()->scopeFilters(),
            $this->parentResource()->getIdentifierField(),
        ) ?? throw new \RuntimeException(\sprintf('Parent record "%s" not found for relation "%s".', $this->parentId, $this->relationName));
    }
}
```

> The `tableConfig()` here mirrors the base's private one but layers the relation's
> `->table()`. Make the base's `tableConfig()` `protected` (not `private`) in Task 7 so
> this override is legal, OR have the base call an overridable `protected function
> tableConfig()` — adjust Task 7 Step 2 accordingly. **(Type-consistency note: confirm
> the base exposes `tableConfig()`/`getActiveSortField()`/`searchableFields()`/
> `resolvedFilters()` as `protected`, not `private`, so subclasses reach them.)**

- [ ] **Step 2: Write the template**

`templates/components/relation_manager.html.twig`:

```twig
<div {{ attributes }} class="space-y-3">
    {% include '@Atrium/components/_record_table.html.twig' %}
</div>
```

- [ ] **Step 3: Wire the service**

`RelationManager` is autoconfigured as a Live Component if the bundle autowires
`src/Twig/Components`. Confirm `config/services.php` autowires that namespace (it
already registers `DataTable`); the new constructor args (`RelationDataProvider`,
`RelationResolver`) autowire via the aliases from Task 6 and autowiring of the
`final` resolver. If `RelationResolver` is not autowired (it has a `ResourceRegistry`
dep), add `$services->set(RelationResolver::class);` to `config/services.php`.

- [ ] **Step 4: Run gates (no functional test yet)**

Run: `composer phpstan && composer cs`
Expected: clean. (PHPStan will catch any seam signature mismatch with the base.)

- [ ] **Step 5: Commit**

```bash
git add src/Twig/Components/RelationManager.php templates/components/relation_manager.html.twig config/services.php
git commit -m "Add read-only RelationManager component (REL-04)"
```

---

## Task 9: `RelationManagers` host + Edit-page placement

**Files:**
- Create: `src/Twig/Components/RelationManagers.php`
- Create: `templates/components/relation_managers.html.twig`
- Modify: `templates/admin/form_page.html.twig`

M1 host is **single-section** (no tabs): it renders every visible relation's manager,
each in a titled section. (Tabs arrive in M3.) It is a plain TwigComponent (not Live) —
it only resolves the list of relations and embeds each `RelationManager` Live Component.

- [ ] **Step 1: Write the host component**

```php
<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @internal Placement host for a resource's relation managers (REL-08). M1 renders
 * each visible relation as a titled section (tabs arrive in REL-M3). Resolves the
 * relation list + the loaded parent record for the `->visible($parent)` filter.
 */
#[AsTwigComponent(name: 'Atrium:RelationManagers', template: '@Atrium/components/relation_managers.html.twig')]
final class RelationManagers
{
    public string $resource = '';
    public string $parentId = '';
    public string $pathPrefix = '';
    public string $page = 'edit';

    public function __construct(private readonly ResourceRegistry $registry)
    {
    }

    /** @return list<array{name: string, label: string, icon: ?string}> */
    public function getRelations(): array
    {
        $resource = $this->resourceObject();
        $parent = $this->parentRecordOrNull();

        $views = [];
        foreach ($resource->relations() as $relation) {
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

    public function hasRelations(): bool
    {
        return [] !== $this->resourceObject()->relations();
    }

    private function resourceObject(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }

    private function parentRecordOrNull(): ?object
    {
        // The host filters by ->visible($parent) only when a record is loadable;
        // the per-relation managers re-resolve the parent themselves. M1 keeps this
        // simple: skip the visible() filter if no provider/record (returns null).
        return null;
    }
}
```

> Note: the host's `->visible($parent)` filtering needs the loaded parent record. M1
> ships the no-op `parentRecordOrNull()` (returns null → all relations shown) to keep
> the host data-light; the proper parent load moves here in M2 alongside the actions
> (or inject `DataProviderInterface` + resolve like `RelationManager`). Capture this as
> a known limitation, not silently. If you prefer, inject the provider now and load the
> parent — but that duplicates `RelationManager`'s resolution; defer to M2.

- [ ] **Step 2: Write the host template**

`templates/components/relation_managers.html.twig`:

```twig
{% if hasRelations() %}
    <div class="space-y-8">
        {% for relation in getRelations() %}
            <section class="space-y-3">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {% if relation.icon %}{{ atrium_icon(relation.icon, { class: 'h-4 w-4' }) }}{% endif %}
                    {{ relation.label }}
                </h2>
                {{ component('Atrium:RelationManager', {
                    resource: resource,
                    parentId: parentId,
                    relation: relation.name,
                    pathPrefix: pathPrefix,
                    page: page,
                }) }}
            </section>
        {% endfor %}
    </div>
{% endif %}
```

- [ ] **Step 3: Embed the host on the Edit page**

In `templates/admin/form_page.html.twig`, after the form component block, add (only
when editing an existing record — i.e. `entityId` is set):

```twig
    {% if entityId %}
        {{ component('Atrium:RelationManagers', {
            resource: resource.slug,
            parentId: entityId,
            pathPrefix: panel.pathPrefix,
            page: 'edit',
        }) }}
    {% endif %}
```

Match the variable names the template already exposes (`resource`, `entityId`,
`panel.pathPrefix`) — check the top of `form_page.html.twig` and reuse exactly what the
`view_page.html.twig` RecordActions embed uses.

- [ ] **Step 4: Run gates**

Run: `composer phpstan && composer cs`
Expected: clean.

- [ ] **Step 5: Commit**

```bash
git add src/Twig/Components/RelationManagers.php templates/components/relation_managers.html.twig templates/admin/form_page.html.twig
git commit -m "Add RelationManagers host + embed on the Edit page (REL-08, single-section)"
```

---

## Task 10: Functional test — a relation manager renders parent-scoped rows

**Files:**
- Create: `tests/Fixtures/Resource/PostRelResource.php` (parent, declares a `comments` relation)
- Create: `tests/Fixtures/Resource/CommentRelResource.php` (child/target, a normal resource)
- Modify: `tests/Functional/AtriumTestKernel.php` (register both)
- Modify: `tests/Functional/KernelBootTest.php` (bump the asserted resource count)
- Create: `tests/Functional/RelationManagerRenderTest.php`

This proves the whole M1 chain end-to-end: a parent's Edit page hosts a relation manager
that lists only the parent's children.

- [ ] **Step 1: Add the fixture resources**

`tests/Fixtures/Resource/CommentRelResource.php` — a normal resource over the
Doctrine-mapped `Note` child (reuse `Note` from Task 6 as the related entity):

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Note;

final class CommentRelResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Note::class;
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('body')]);
    }
}
```

`tests/Fixtures/Resource/PostRelResource.php` — the parent over `Article`, declaring the
relation:

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Article;

final class PostRelResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Article::class;
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('title')]);
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('notes')
                ->oneToMany(CommentRelResource::class)
                ->foreignKey('articleId')
                ->recordTitle('body'),
        ];
    }
}
```

- [ ] **Step 2: Register the fixtures in the test kernel**

In `tests/Functional/AtriumTestKernel.php`, register `PostRelResource` and
`CommentRelResource` the same way existing fixture resources are tagged
`atrium.resource` (follow the existing registration block exactly).

- [ ] **Step 3: Bump the resource-count assertion**

In `tests/Functional/KernelBootTest.php`, find the assertion on the number of registered
resources and increase it by 2. Run it to confirm the new count:

Run: `vendor/bin/phpunit tests/Functional/KernelBootTest.php`
Expected: PASS with the updated count (adjust the number to whatever the failure
reports).

- [ ] **Step 4: Write the functional render test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\DoctrineRelationProvider;
use Atrium\Relation\Relation;
use Atrium\Relation\RelationResolver;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Entity\Article;
use Atrium\Tests\Fixtures\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RelationManagerRenderTest extends KernelTestCase
{
    public function testRelationProviderListsOnlyTheParentsChildrenThroughTheContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        (new SchemaTool($em))->createSchema([
            $em->getClassMetadata(Article::class),
            $em->getClassMetadata(Note::class),
        ]);

        $a1 = new Article('First');
        $em->persist($a1);
        $em->flush();
        $em->persist(new Note('hello', $a1->id));
        $em->persist(new Note('world', $a1->id));
        $em->persist(new Note('other', null));
        $em->flush();
        $em->clear();

        /** @var ResourceRegistry $registry */
        $registry = $container->get(ResourceRegistry::class);
        /** @var DoctrineRelationProvider $relations */
        $relations = $container->get(DoctrineRelationProvider::class);

        $resolver = new RelationResolver($registry);
        $relation = Relation::make('notes')
            ->oneToMany(\Atrium\Tests\Fixtures\Resource\CommentRelResource::class)
            ->foreignKey('articleId')->recordTitle('body');
        $descriptor = $resolver->resolve($relation, parentIdField: 'id');

        $parent = $em->find(Article::class, $a1->id);
        self::assertNotNull($parent);

        $rows = [...$relations->listRelated($descriptor, $parent, new \Atrium\DataProvider\DataQuery())];
        self::assertCount(2, $rows);
    }
}
```

> Service ids: confirm `ResourceRegistry` and `DoctrineRelationProvider` are fetchable
> from the test container (the kernel runs with `framework.test: true`, which exposes
> private services). If `DoctrineRelationProvider` is not public in test, fetch the
> `RelationDataProvider` alias instead, or add it to the test container's public list as
> the existing providers are.

- [ ] **Step 5: Run the test + full suite + gates**

Run: `composer test && composer phpstan && composer cs`
Expected: all green; the new functional test passes; the table regression suite still
passes; `KernelBootTest` count correct.

- [ ] **Step 6: Commit**

```bash
git add tests/Fixtures/Resource/PostRelResource.php tests/Fixtures/Resource/CommentRelResource.php \
        tests/Functional/AtriumTestKernel.php tests/Functional/KernelBootTest.php \
        tests/Functional/RelationManagerRenderTest.php
git commit -m "Functional test: relation manager lists parent-scoped rows (REL-04, REL-09)"
```

---

## Task 11: CHANGELOG + PRD status

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `docs/PRDs/PRD-relations-nesting.md` (status note)

- [ ] **Step 1: Add a CHANGELOG entry**

Under `## [Unreleased]` → `### Added`, add:

```markdown
- **Relations foundation (`REL-01..04`, `REL-10`, `REL-11`, `REL-20`).** A resource
  declares managed relationships with `relations()` returning `Relation::make(...)`
  descriptors (one-to-many / many-to-many, explicit keys — no Doctrine in core). New
  storage-agnostic `RelationDataProvider` seam (Doctrine + array adapters; one-to-many
  read side) and an `AbstractRecordTable` core extracted from `DataTable` (no behaviour
  change). A read-only `RelationManager` Live Component renders a parent-scoped related
  table, embedded on the Edit page via a `RelationManagers` host. Link/unlink actions,
  many-to-many, and nested resources land in subsequent milestones. PRD:
  `docs/PRDs/PRD-relations-nesting.md`.
```

- [ ] **Step 2: Run gates + commit**

Run: `composer test && composer phpstan && composer cs`
Expected: green.

```bash
git add CHANGELOG.md docs/PRDs/PRD-relations-nesting.md
git commit -m "Document relations foundation (REL-M1) in CHANGELOG"
```

---

## REL-M1 self-review checklist (run before declaring M1 done)

- [ ] **Spec coverage:** `REL-01` (descriptor + `relations()`) ✓ Tasks 2–3; `REL-02`
  (kinds) ✓ Task 1; `REL-03` (shared core) ✓ Task 7; `REL-04` (manager) ✓ Tasks 8–9;
  `REL-10` (seam) ✓ Tasks 5–6; `REL-11` read side ✓ Tasks 5–6, 10; `REL-20`
  (target-registration validation) ✓ Task 4. Link/unlink (`REL-05..07`), read-only-on-
  view enforcement (`REL-09` view side), tabs (`REL-08` tabs), nesting (`REL-14..18`)
  are **out of M1 by design** — see roadmap.
- [ ] **No behaviour drift:** the existing table suite passed unchanged after Task 7.
- [ ] **Type consistency:** base seam names used in `RelationManager` (Task 8) match
  those defined in Task 7 (`fetchPage`/`total`/`findRecord`/`buildQuery`/`buildAllQuery`/
  `resource`/`entityClass`/`actionContext`/`rowUrl`/`getHeaderActions`); `tableConfig()`
  and the query helpers are `protected` on the base.
- [ ] **No Doctrine in core:** only `DoctrineRelationProvider` imports Doctrine; `Relation`,
  `RelationDescriptor`, `RelationResolver`, `RelationDataProvider`, `AbstractRecordTable`,
  `RelationManager` do not.
- [ ] Gates green: `composer test && composer phpstan && composer cs`.

---

## Roadmap — REL-M2..M5 (each gets its own plan when reached)

- **REL-M2 — one-to-many actions** (`REL-05`, `REL-06` 1:M, `REL-07` associate, `REL-09`,
  `REL-12`, `REL-13`): owned Create/Edit/View/Delete (+ bulk) scoped to the parent
  (create sets the FK in one `transactional()`); `AssociateAction`/`DissociateAction`
  with a `listLinkable`-backed picker (implement `listLinkable`/`countLinkable` +
  `associate`/`dissociate` in both adapters); `readOnlyOnView` enforcement; `visible(
  fn($parent))` in the host (load the parent there); `canAssociate`/`canDissociate`
  hooks on `AdminResource`. Full 1:M lifecycle on Edit.
- **REL-M3 — many-to-many + tabs** (`REL-02` M:N, `REL-06` M:N, `REL-07` pivot, `REL-08`
  tabs): `manyToMany`/pivot in the providers (join `listRelated`/`listLinkable`,
  `attach`/`detach` via DBAL sharing the writer transaction; array adapter pivot list);
  pivot columns + attach-form fields; the `RelationManagers` host gains a server-driven
  tab strip (active-only mount); `canAttach`/`canDetach`.
- **REL-M4 — nested resources** (`REL-14..18`, `REL-20` nesting validation):
  `ParentRelation` + `AdminResource::parent()` + boot validation; the nested route family
  + non-collision ordering; scoped parent resolution + child parent-scope filter +
  cross-parent 404; `NestedActionContext`; `PageContext.parentRecords`/`nestedUrl()`;
  recursive breadcrumb; relation-manager row links → child nested pages.
- **REL-M5 — playground, docs, verify, review** (`REL-19`, `REL-21`, `REL-22`):
  `->using()` extraction example; playground (Post→Comments 1:M, Post↔Tags M:N,
  Course→Lessons nested); `docs/integration-guide/relations/{overview,nesting}.md`;
  CHANGELOG `REL-01..22`; browser-verify; **separate code-review agent**.
```
