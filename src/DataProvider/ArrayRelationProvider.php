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
        $matched = $this->matchingChildren($relation, $parent, $query);

        return \array_slice($matched, max(0, $query->offset), max(1, $query->limit));
    }

    public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        return \count($this->matchingChildren($relation, $parent, $query));
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
        throw new \LogicException('attach() is implemented in REL-M3.');
    }

    public function detach(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('detach() is implemented in REL-M3.');
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
            throw new \LogicException('Many-to-many relations are implemented in REL-M3.');
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
