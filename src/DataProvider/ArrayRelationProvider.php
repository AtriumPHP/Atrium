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

    /**
     * @param array<class-string, list<object>>                                                                          $records
     * @param array<string, list<array{parent: scalar|null, related: scalar|null, columns: array<string, scalar|null>}>> $pivots  in-memory pivot rows, keyed by pivot table name
     */
    public function __construct(
        private array $records = [],
        ?PropertyAccessorInterface $accessor = null,
        private array $pivots = [],
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function listRelated(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
    {
        $matched = $relation->kind->usesPivot()
            ? $this->relatedThroughPivot($relation, $parent, $query)
            : $this->matchingChildren($relation, $parent, $query);

        return \array_slice($matched, max(0, $query->offset), max(1, $query->limit));
    }

    public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        $matched = $relation->kind->usesPivot()
            ? $this->relatedThroughPivot($relation, $parent, $query)
            : $this->matchingChildren($relation, $parent, $query);

        return \count($matched);
    }

    public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
    {
        $matched = $relation->kind->usesPivot()
            ? $this->linkableThroughPivot($relation, $parent, $query)
            : $this->linkableChildren($relation, $query);

        return \array_slice($matched, max(0, $query->offset), max(1, $query->limit));
    }

    public function countLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        $matched = $relation->kind->usesPivot()
            ? $this->linkableThroughPivot($relation, $parent, $query)
            : $this->linkableChildren($relation, $query);

        return \count($matched);
    }

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

    public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void
    {
        $this->assertManyToMany($relation);
        $table = (string) $relation->pivotTable;
        $parentId = $this->scalarOrNull($this->parentId($relation, $parent));
        $relatedId = $this->scalarOrNull($this->childId($child, $relation));

        // Idempotent, mirroring a unique pivot key on the Doctrine side: re-attaching
        // an already-linked pair is a no-op rather than a duplicate tuple.
        foreach ($this->pivots[$table] ?? [] as $row) {
            if ($row['parent'] === $parentId && $row['related'] === $relatedId) {
                return;
            }
        }

        $this->pivots[$table][] = [
            'parent' => $parentId,
            'related' => $relatedId,
            'columns' => $pivot,
        ];
    }

    public function detach(RelationDescriptor $relation, object $parent, object $child): void
    {
        $this->assertManyToMany($relation);
        $table = (string) $relation->pivotTable;
        $parentId = $this->scalarOrNull($this->parentId($relation, $parent));
        $relatedId = $this->scalarOrNull($this->childId($child, $relation));

        $this->pivots[$table] = array_values(array_filter(
            $this->pivots[$table] ?? [],
            static fn (array $row): bool => !($row['parent'] === $parentId && $row['related'] === $relatedId),
        ));
    }

    /**
     * Children of $parent for a one-to-many relation: child.<foreignKey> equals
     * the parent's identifier value, also satisfying every equality condition in
     * the query's filters (which carry the target resource's `scopeQuery()`, so a
     * relation can never surface a row the resource itself would hide — REL-09).
     *
     * @return list<object>
     */
    private function matchingChildren(RelationDescriptor $relation, object $parent, DataQuery $query): array
    {
        $this->assertOneToMany($relation);

        $parentId = $this->parentId($relation, $parent);
        $foreignKey = (string) $relation->foreignKey;

        $children = array_values(array_filter(
            $this->records[$relation->childEntityClass] ?? [],
            fn (object $child): bool => $this->accessor->isReadable($child, $foreignKey)
                && $this->accessor->getValue($child, $foreignKey) === $parentId,
        ));

        return $this->applyArrayFilters($children, $query);
    }

    /**
     * Children with no parent yet (FK is null), narrowed by the query filters
     * (which carry the target resource's scopeQuery — a relation never offers a
     * row the resource itself would hide).
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

        return $this->applyArrayFilters($children, $query);
    }

    /**
     * Children linked to $parent through the pivot table (many-to-many), narrowed
     * by the query filters (the target's scopeQuery).
     *
     * @return list<object>
     */
    private function relatedThroughPivot(RelationDescriptor $relation, object $parent, DataQuery $query): array
    {
        $relatedIds = $this->pivotIdStrings($relation, $parent);
        $children = array_values(array_filter(
            $this->records[$relation->childEntityClass] ?? [],
            fn (object $child): bool => null !== ($id = $this->childIdString($child, $relation)) && \in_array($id, $relatedIds, true),
        ));

        return $this->applyArrayFilters($children, $query);
    }

    /**
     * Children NOT yet linked to $parent through the pivot (many-to-many linkable).
     *
     * @return list<object>
     */
    private function linkableThroughPivot(RelationDescriptor $relation, object $parent, DataQuery $query): array
    {
        $linkedIds = $this->pivotIdStrings($relation, $parent);
        $children = array_values(array_filter(
            $this->records[$relation->childEntityClass] ?? [],
            fn (object $child): bool => null === ($id = $this->childIdString($child, $relation)) || !\in_array($id, $linkedIds, true),
        ));

        return $this->applyArrayFilters($children, $query);
    }

    /**
     * The pivot's related ids for $parent, coerced to strings for type-safe
     * (strict) matching against a child's id — mirroring the Doctrine adapter,
     * where an integer FK and a string-typed id compare equal at the DB level.
     *
     * @return list<string>
     */
    private function pivotIdStrings(RelationDescriptor $relation, object $parent): array
    {
        return array_map(static fn (mixed $id): string => (string) $id, $this->pivotRelatedIds($relation, $parent));
    }

    private function childIdString(object $child, RelationDescriptor $relation): ?string
    {
        $id = $this->scalarOrNull($this->childId($child, $relation));

        return null === $id ? null : (string) $id;
    }

    /**
     * Related child ids linked to $parent in the relation's pivot table.
     *
     * @return list<scalar|null>
     */
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

    private function scalarOrNull(mixed $value): string|int|float|bool|null
    {
        return \is_scalar($value) ? $value : null;
    }

    /**
     * Narrow $children by every equality condition in the query's filters.
     *
     * @param list<object> $children
     *
     * @return list<object>
     */
    private function applyArrayFilters(array $children, DataQuery $query): array
    {
        foreach ($query->filters as $field => $value) {
            $children = array_values(array_filter(
                $children,
                fn (object $child): bool => $this->matchesFilter($child, (string) $field, $value),
            ));
        }

        return $children;
    }

    private function assertOneToMany(RelationDescriptor $relation): void
    {
        if (RelationKind::OneToMany !== $relation->kind) {
            throw new \LogicException('This relation operation requires a one-to-many relation.');
        }
    }

    private function assertManyToMany(RelationDescriptor $relation): void
    {
        if (RelationKind::ManyToMany !== $relation->kind) {
            throw new \LogicException('This relation operation requires a many-to-many relation.');
        }
    }

    private function parentId(RelationDescriptor $relation, object $parent): mixed
    {
        return $this->accessor->getValue($parent, $relation->parentIdField);
    }

    private function matchesFilter(object $child, string $field, string|int|float|bool|null $value): bool
    {
        if (!$this->accessor->isReadable($child, $field)) {
            return null === $value;
        }

        $actual = $this->accessor->getValue($child, $field);

        if (\is_bool($value)) {
            return (bool) $actual === $value;
        }
        if (null === $value) {
            return null === $actual;
        }

        $actual = $actual instanceof \BackedEnum ? $actual->value : $actual;

        return \is_scalar($actual) && (string) $actual === (string) $value;
    }
}
