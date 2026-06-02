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
}
