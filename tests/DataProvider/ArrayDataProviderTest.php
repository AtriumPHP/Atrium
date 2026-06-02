<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\ArrayDataProvider;
use Atrium\DataProvider\DataQuery;
use Atrium\Tests\Fixtures\Entity\Story;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Entity\Writer;
use PHPUnit\Framework\TestCase;

final class ArrayDataProviderTest extends TestCase
{
    private ArrayDataProvider $provider;

    protected function setUp(): void
    {
        $tags = [];
        for ($i = 1; $i <= 30; ++$i) {
            $tags[] = new Tag($i, \sprintf('Tag %02d', $i), \sprintf('tag-%02d', $i));
        }

        $this->provider = new ArrayDataProvider([Tag::class => $tags]);
    }

    public function testCountAndDefaultLimit(): void
    {
        self::assertSame(30, $this->provider->count(Tag::class, new DataQuery()));
        self::assertCount(25, [...$this->provider->fetch(Tag::class, new DataQuery())]);
    }

    public function testUnknownClassIsEmpty(): void
    {
        self::assertSame(0, $this->provider->count(self::class, new DataQuery()));
    }

    public function testPagination(): void
    {
        self::assertCount(10, [...$this->provider->fetch(Tag::class, new DataQuery(offset: 0, limit: 10))]);
        self::assertCount(5, [...$this->provider->fetch(Tag::class, new DataQuery(offset: 25, limit: 10))]);
    }

    public function testSearchAcrossFields(): void
    {
        $query = new DataQuery(search: 'tag-07', searchableFields: ['slug']);

        self::assertSame(1, $this->provider->count(Tag::class, $query));

        $rows = [...$this->provider->fetch(Tag::class, $query)];
        self::assertInstanceOf(Tag::class, $rows[0]);
        self::assertSame('tag-07', $rows[0]->slug);
    }

    public function testSortDescending(): void
    {
        $rows = [...$this->provider->fetch(Tag::class, new DataQuery(sortField: 'id', sortDirection: 'desc', limit: 1))];

        self::assertInstanceOf(Tag::class, $rows[0]);
        self::assertSame(30, $rows[0]->id);
    }

    public function testFindResolvesByIdAndMissesAreNull(): void
    {
        $found = $this->provider->find(Tag::class, '7');

        self::assertInstanceOf(Tag::class, $found);
        self::assertSame(7, $found->id);
        self::assertNull($this->provider->find(Tag::class, '999'));
    }

    public function testFindAppliesScopeFilters(): void
    {
        $provider = new ArrayDataProvider([Tag::class => [
            new Tag(1, 'Mine', 'mine', kind: 'a'),
            new Tag(2, 'Theirs', 'theirs', kind: 'b'),
        ]]);

        // In scope: resolves as usual.
        self::assertNotNull($provider->find(Tag::class, '1', ['kind' => 'a']));
        // Out of scope: invisible even though the id exists.
        self::assertNull($provider->find(Tag::class, '2', ['kind' => 'a']));
    }

    public function testRelationColumnSearchSortAndFilterTraverseNestedPaths(): void
    {
        // Plain objects with a nested relation — no Doctrine: the array provider
        // reads dotted paths through the property accessor for free.
        $provider = new ArrayDataProvider([Story::class => [
            new Story('Engines', new Writer('Grace')),
            new Story('Looms', new Writer('Ada')),
            new Story('Anonymous', null),
        ]]);

        // Search across the relation field.
        $searched = [...$provider->fetch(Story::class, new DataQuery(search: 'Ada', searchableFields: ['writer.name']))];
        self::assertCount(1, $searched);
        self::assertInstanceOf(Story::class, $searched[0]);
        self::assertSame('Looms', $searched[0]->title);

        // Filter by the relation field.
        $filtered = [...$provider->fetch(Story::class, new DataQuery(filters: ['writer.name' => 'Grace']))];
        self::assertCount(1, $filtered);
        self::assertInstanceOf(Story::class, $filtered[0]);
        self::assertSame('Engines', $filtered[0]->title);

        // Sort by the relation field (null relation tolerated).
        $sorted = array_map(
            static fn (object $r): string => $r instanceof Story ? $r->title : '',
            [...$provider->fetch(Story::class, new DataQuery(sortField: 'writer.name', sortDirection: 'asc'))],
        );
        $named = array_values(array_filter($sorted, static fn (string $t): bool => 'Anonymous' !== $t));
        self::assertSame(['Looms', 'Engines'], $named);
    }
}
