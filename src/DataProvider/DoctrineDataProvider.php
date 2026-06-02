<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Default {@see DataProviderInterface} adapter, backed by Doctrine ORM.
 *
 * Builds a {@see QueryBuilder} from a {@see DataQuery}: the search term is
 * applied as a parameter-bound LIKE across the trusted searchable fields, and
 * sorting/pagination map straight onto the query. This is the only place in the
 * foundation that references Doctrine types — the core abstractions must not.
 */
final readonly class DoctrineDataProvider implements DataProviderInterface
{
    private const string ALIAS = 'e';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fetch(string $entityClass, DataQuery $query): iterable
    {
        $qb = $this->createBaseQuery($entityClass, $query)
            ->setFirstResult(max(0, $query->offset))
            ->setMaxResults(max(1, $query->limit));

        if (null !== $query->sortField) {
            $qb->orderBy(self::ALIAS.'.'.$query->sortField, $query->normalizedSortDirection());
        }

        return array_values(array_filter(
            (array) $qb->getQuery()->getResult(),
            static fn (mixed $row): bool => \is_object($row),
        ));
    }

    public function count(string $entityClass, DataQuery $query): int
    {
        $qb = $this->createBaseQuery($entityClass, $query)
            ->select(\sprintf('COUNT(%s)', self::ALIAS));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function find(string $entityClass, int|string $id, array $filters = []): ?object
    {
        // No scope: the identity map makes em->find the fast, cache-friendly path.
        if ([] === $filters) {
            return $this->entityManager->find($entityClass, $id);
        }

        // A scoped lookup: the record must match its id *and* every scope
        // condition, so an out-of-scope id resolves to null.
        $idField = $this->entityManager->getClassMetadata($entityClass)->getSingleIdentifierFieldName();
        $qb = $this->entityManager->getRepository($entityClass)->createQueryBuilder(self::ALIAS);
        $qb->where($qb->expr()->eq(self::ALIAS.'.'.$idField, ':atrium_id'))
            ->setParameter('atrium_id', $id);
        $this->applyFilters($qb, $filters);

        $result = $qb->getQuery()->getOneOrNullResult();

        return \is_object($result) ? $result : null;
    }

    /**
     * @param class-string $entityClass
     */
    private function createBaseQuery(string $entityClass, DataQuery $query): QueryBuilder
    {
        $qb = $this->entityManager->getRepository($entityClass)->createQueryBuilder(self::ALIAS);

        $term = $query->searchTerm();
        if (null !== $term) {
            $orX = $qb->expr()->orX();
            foreach (array_values($query->searchableFields) as $index => $field) {
                $parameter = 'atrium_search_'.$index;
                $orX->add($qb->expr()->like(self::ALIAS.'.'.$field, ':'.$parameter));
                $qb->setParameter($parameter, '%'.$term.'%');
            }
            $qb->andWhere($orX);
        }

        $this->applyFilters($qb, $query->filters);

        return $qb;
    }

    /**
     * Apply equality (and IS NULL) scope/filter conditions, binding every value
     * as a parameter — field names come from trusted developer configuration.
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

            $parameter = 'atrium_filter_'.$index++;
            $qb->andWhere($qb->expr()->eq($column, ':'.$parameter));
            $qb->setParameter($parameter, $value);
        }
    }
}
