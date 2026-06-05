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
        $qb = $this->relatedQuery($relation, $parent, $query)->select(\sprintf('COUNT(%s)', self::ALIAS));

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
        $this->setForeignKey($relation, $child, $this->accessor->getValue($parent, $relation->parentIdField));
    }

    public function dissociate(RelationDescriptor $relation, object $parent, object $child): void
    {
        $this->setForeignKey($relation, $child, null);
    }

    /**
     * Set (or clear) the child's foreign key and persist. The flush enlists in the
     * ambient transaction opened by the relation manager (so a create+associate
     * commits or rolls back together).
     */
    private function setForeignKey(RelationDescriptor $relation, object $child, mixed $value): void
    {
        if (RelationKind::OneToMany !== $relation->kind) {
            throw new \LogicException('Many-to-many link/unlink is implemented in REL-M3.');
        }

        $this->accessor->setValue($child, (string) $relation->foreignKey, $value);
        $this->entityManager->persist($child);
        $this->entityManager->flush();
    }

    public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void
    {
        throw new \LogicException('attach() is implemented in REL-M3.');
    }

    public function detach(RelationDescriptor $relation, object $parent, object $child): void
    {
        throw new \LogicException('detach() is implemented in REL-M3.');
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
