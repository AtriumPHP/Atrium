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
        if ($relation->kind->usesPivot()) {
            return $this->childrenByIds($relation, $this->pivotRelatedIds($relation, $parent), $query, exclude: false);
        }

        $qb = $this->relatedQuery($relation, $parent, $query)
            ->setFirstResult(max(0, $query->offset))
            ->setMaxResults(max(1, $query->limit));

        return array_values(array_filter(
            (array) $qb->getQuery()->getResult(),
            static fn (mixed $row): bool => \is_object($row),
        ));
    }

    public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int
    {
        if ($relation->kind->usesPivot()) {
            return $this->countByIds($relation, $this->pivotRelatedIds($relation, $parent), $query, exclude: false);
        }

        $qb = $this->relatedQuery($relation, $parent, $query)->select(\sprintf('COUNT(%s)', self::ALIAS));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable
    {
        if ($relation->kind->usesPivot()) {
            return $this->childrenByIds($relation, $this->pivotRelatedIds($relation, $parent), $query, exclude: true);
        }

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
        if ($relation->kind->usesPivot()) {
            return $this->countByIds($relation, $this->pivotRelatedIds($relation, $parent), $query, exclude: true);
        }

        $qb = $this->linkableQuery($relation, $query)->select(\sprintf('COUNT(%s)', self::ALIAS));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function associate(RelationDescriptor $relation, object $parent, object $child): void
    {
        $this->setForeignKey($relation, $child, $this->accessor->getValue($parent, $relation->parentIdField));
    }

    public function dissociate(RelationDescriptor $relation, object $parent, object $child): void
    {
        $this->setForeignKey($relation, $child, null);
    }

    /**
     * Set (or clear) the child's foreign key and persist. When called inside the
     * relation manager's `writer->transactional()` (Doctrine `wrapInTransaction`),
     * this flush executes its SQL within that already-open transaction, so the
     * link commits or rolls back together with the surrounding work.
     */
    private function setForeignKey(RelationDescriptor $relation, object $child, mixed $value): void
    {
        if (RelationKind::OneToMany !== $relation->kind) {
            throw new \LogicException('associate()/dissociate() require a one-to-many relation; use attach()/detach() for many-to-many.');
        }

        $this->accessor->setValue($child, (string) $relation->foreignKey, $value);
        $this->entityManager->persist($child);
        $this->entityManager->flush();
    }

    public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void
    {
        $this->assertManyToMany($relation);
        $connection = $this->entityManager->getConnection();
        $parentValue = $this->accessor->getValue($parent, $relation->parentIdField);
        $relatedValue = $this->accessor->getValue($child, $relation->childIdField);

        // Idempotent (parity with the array adapter, and safe against a unique pivot
        // key): re-attaching an already-linked pair is a no-op rather than a
        // duplicate-row insert or a unique-constraint 500.
        $existing = $connection->fetchOne(
            \sprintf(
                'SELECT 1 FROM %s WHERE %s = ? AND %s = ?',
                $connection->quoteIdentifier((string) $relation->pivotTable),
                $connection->quoteIdentifier((string) $relation->pivotParentKey),
                $connection->quoteIdentifier((string) $relation->pivotRelatedKey),
            ),
            [$parentValue, $relatedValue],
        );
        if (false !== $existing) {
            return;
        }

        $data = [
            (string) $relation->pivotParentKey => $parentValue,
            (string) $relation->pivotRelatedKey => $relatedValue,
        ];
        foreach ($relation->pivotColumns as $column) {
            $data[$column] = $pivot[$column] ?? null;
        }

        // Connection::insert quotes identifiers and binds every value as a
        // parameter; column/table names come from the trusted descriptor.
        $connection->insert((string) $relation->pivotTable, $data);
    }

    public function detach(RelationDescriptor $relation, object $parent, object $child): void
    {
        $this->assertManyToMany($relation);
        $this->entityManager->getConnection()->delete((string) $relation->pivotTable, [
            (string) $relation->pivotParentKey => $this->accessor->getValue($parent, $relation->parentIdField),
            (string) $relation->pivotRelatedKey => $this->accessor->getValue($child, $relation->childIdField),
        ]);
    }

    private function relatedQuery(RelationDescriptor $relation, object $parent, DataQuery $query): QueryBuilder
    {
        if (RelationKind::OneToMany !== $relation->kind) {
            throw new \LogicException('Many-to-many listing is implemented in REL-M3.');
        }

        $parentId = $this->accessor->getValue($parent, $relation->parentIdField);
        $qb = $this->entityManager->getRepository($relation->childEntityClass)->createQueryBuilder(self::ALIAS);
        $qb->where($qb->expr()->eq(self::ALIAS.'.'.$relation->foreignKey, ':atrium_parent_id'))
            ->setParameter('atrium_parent_id', $parentId);

        // The query carries the target resource's scopeQuery() conditions, so a
        // relation can never surface a row the resource itself would hide (REL-09).
        $this->applyFilters($qb, $query->filters);

        return $qb;
    }

    /**
     * Candidates for an Associate picker: one-to-many children with no parent yet
     * (FK IS NULL), narrowed by the target resource's scopeQuery (query filters).
     */
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

    /**
     * Related child ids linked to $parent through the relation's pivot table. One
     * DBAL query; table/column names come from the trusted descriptor (developer
     * config, never user input), the parent id bound as a parameter.
     *
     * @return list<scalar>
     */
    private function pivotRelatedIds(RelationDescriptor $relation, object $parent): array
    {
        if (!$relation->kind->usesPivot()) {
            throw new \LogicException('Pivot lookup requires a many-to-many relation.');
        }

        $parentId = $this->accessor->getValue($parent, $relation->parentIdField);
        $connection = $this->entityManager->getConnection();
        // Quote identifiers (the descriptor's pivot table/columns are trusted dev
        // config, but quoting keeps reserved-word names portable across MySQL/PG);
        // the parent id is bound as a parameter.
        $sql = \sprintf(
            'SELECT %s FROM %s WHERE %s = ?',
            $connection->quoteIdentifier((string) $relation->pivotRelatedKey),
            $connection->quoteIdentifier((string) $relation->pivotTable),
            $connection->quoteIdentifier((string) $relation->pivotParentKey),
        );

        return array_values(array_filter(
            $connection->fetchFirstColumn($sql, [$parentId]),
            static fn (mixed $value): bool => \is_scalar($value),
        ));
    }

    /**
     * Children whose id is IN (or NOT IN, when $exclude) the given id set, with the
     * target scope filters, pagination and sort applied. For the IN case an empty
     * set means no rows; for NOT-IN an empty set means no exclusion (all children).
     *
     * @param list<scalar> $ids
     *
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

        return array_values(array_filter(
            (array) $qb->getQuery()->getResult(),
            static fn (mixed $row): bool => \is_object($row),
        ));
    }

    /**
     * @param list<scalar> $ids
     */
    private function countByIds(RelationDescriptor $relation, array $ids, DataQuery $query, bool $exclude): int
    {
        if (!$exclude && [] === $ids) {
            return 0;
        }

        $qb = $this->idScopedQuery($relation, $ids, $exclude, $query)->select(\sprintf('COUNT(%s)', self::ALIAS));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<scalar> $ids
     */
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

    private function assertManyToMany(RelationDescriptor $relation): void
    {
        if (!$relation->kind->usesPivot()) {
            throw new \LogicException('This relation operation requires a many-to-many relation.');
        }
    }

    /**
     * Apply equality (and IS NULL) conditions, binding every value as a parameter.
     * Field names come from trusted developer configuration (the resource scope).
     *
     * @param array<string, scalar|bool|null> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $index = 0;
        foreach ($filters as $field => $value) {
            $column = self::ALIAS.'.'.$field;
            if (null === $value) {
                $qb->andWhere($qb->expr()->isNull($column));

                continue;
            }

            $parameter = 'atrium_rel_filter_'.$index++;
            $qb->andWhere($qb->expr()->eq($column, ':'.$parameter));
            $qb->setParameter($parameter, $value);
        }
    }
}
