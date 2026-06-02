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

    public function find(string $entityClass, int|string $id): ?object
    {
        return $this->entityManager->find($entityClass, $id);
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

        return $qb;
    }
}
