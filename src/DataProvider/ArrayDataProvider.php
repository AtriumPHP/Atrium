<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * In-memory {@see DataProviderInterface} adapter over plain PHP objects.
 *
 * Useful for tests, fixtures and demos, and a proof that the data layer is not
 * tied to Doctrine. Search/sort/pagination are applied in PHP via
 * property-access reads of the configured fields.
 */
final class ArrayDataProvider implements DataProviderInterface
{
    private readonly PropertyAccessorInterface $accessor;

    /**
     * @param array<class-string, list<object>> $records records indexed by entity class
     */
    public function __construct(
        private array $records = [],
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function fetch(string $entityClass, DataQuery $query): iterable
    {
        $rows = $this->filtered($entityClass, $query);

        if (null !== $query->sortField) {
            $rows = $this->sorted($rows, $query->sortField, $query->normalizedSortDirection());
        }

        return \array_slice($rows, max(0, $query->offset), max(1, $query->limit));
    }

    public function count(string $entityClass, DataQuery $query): int
    {
        return \count($this->filtered($entityClass, $query));
    }

    /**
     * @param class-string $entityClass
     *
     * @return list<object>
     */
    private function filtered(string $entityClass, DataQuery $query): array
    {
        $rows = $this->records[$entityClass] ?? [];

        $term = $query->searchTerm();
        if (null === $term) {
            return array_values($rows);
        }

        $needle = mb_strtolower($term);

        return array_values(array_filter($rows, function (object $row) use ($query, $needle): bool {
            foreach ($query->searchableFields as $field) {
                $value = $this->readString($row, $field);
                if (null !== $value && str_contains(mb_strtolower($value), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param list<object> $rows
     *
     * @return list<object>
     */
    private function sorted(array $rows, string $field, string $direction): array
    {
        usort($rows, function (object $a, object $b) use ($field, $direction): int {
            $comparison = $this->readComparable($a, $field) <=> $this->readComparable($b, $field);

            return DataQuery::SORT_DESC === $direction ? -$comparison : $comparison;
        });

        return $rows;
    }

    private function read(object $row, string $field): mixed
    {
        if (!$this->accessor->isReadable($row, $field)) {
            return null;
        }

        return $this->accessor->getValue($row, $field);
    }

    private function readString(object $row, string $field): ?string
    {
        $value = $this->read($row, $field);

        return \is_scalar($value) || $value instanceof \Stringable ? (string) $value : null;
    }

    private function readComparable(object $row, string $field): int|float|string|null
    {
        $value = $this->read($row, $field);

        return match (true) {
            $value instanceof \DateTimeInterface => $value->getTimestamp(),
            \is_int($value), \is_float($value), \is_string($value), null === $value => $value,
            \is_bool($value) => (int) $value,
            $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }
}
