<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\DataQuery;
use PHPUnit\Framework\TestCase;

final class DataQueryTest extends TestCase
{
    public function testHasNoSearchByDefault(): void
    {
        $query = new DataQuery();

        self::assertFalse($query->hasSearch());
        self::assertNull($query->searchTerm());
    }

    public function testSearchRequiresBothATermAndFields(): void
    {
        self::assertFalse((new DataQuery(search: 'abc'))->hasSearch());
        self::assertFalse((new DataQuery(search: '  ', searchableFields: ['name']))->hasSearch());
        self::assertTrue((new DataQuery(search: 'abc', searchableFields: ['name']))->hasSearch());
    }

    public function testSearchTermIsTrimmed(): void
    {
        self::assertSame('abc', (new DataQuery(search: '  abc  ', searchableFields: ['name']))->searchTerm());
    }

    public function testSortDirectionIsNormalised(): void
    {
        self::assertSame('asc', (new DataQuery())->normalizedSortDirection());
        self::assertSame('desc', (new DataQuery(sortDirection: 'DESC'))->normalizedSortDirection());
        self::assertSame('asc', (new DataQuery(sortDirection: 'nonsense'))->normalizedSortDirection());
    }

    public function testWithFiltersMergesAndPreservesTheRestOfTheQuery(): void
    {
        $base = new DataQuery(
            search: 'abc',
            searchableFields: ['name'],
            sortField: 'name',
            sortDirection: 'desc',
            offset: 10,
            limit: 5,
            filters: ['tenantId' => 7],
        );

        $scoped = $base->withFilters(['deletedAt' => null, 'tenantId' => 9]);

        // Other facets are carried over untouched...
        self::assertSame('abc', $scoped->search);
        self::assertSame(['name'], $scoped->searchableFields);
        self::assertSame('name', $scoped->sortField);
        self::assertSame(10, $scoped->offset);
        self::assertSame(5, $scoped->limit);
        // ...and the new conditions merge, last value winning per field.
        self::assertSame(['tenantId' => 9, 'deletedAt' => null], $scoped->filters);
        // The original is untouched (immutable).
        self::assertSame(['tenantId' => 7], $base->filters);
    }
}
