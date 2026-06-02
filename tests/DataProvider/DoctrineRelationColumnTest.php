<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DoctrineDataProvider;
use Atrium\Tests\Fixtures\Doctrine\EntityManagerFactory;
use Atrium\Tests\Fixtures\Entity\Story;
use Atrium\Tests\Fixtures\Entity\Writer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * A dotted field name (`writer.name`) is resolved to a LEFT JOIN, so a relation
 * column can be searched, sorted and filtered through the Doctrine adapter — the
 * one place joins are allowed to live.
 */
final class DoctrineRelationColumnTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineDataProvider $provider;

    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::create();
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Writer::class),
            $this->entityManager->getClassMetadata(Story::class),
        ]);
        $this->provider = new DoctrineDataProvider($this->entityManager);

        $ada = new Writer('Ada');
        $grace = new Writer('Grace');
        $orphan = null; // a story with no writer, to prove LEFT (not INNER) join
        $this->entityManager->persist($ada);
        $this->entityManager->persist($grace);
        $this->entityManager->persist(new Story('Engines', $grace));
        $this->entityManager->persist(new Story('Looms', $ada));
        $this->entityManager->persist(new Story('Anonymous', $orphan));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testSortByRelationField(): void
    {
        $titles = array_map(
            static fn (object $s): string => $s instanceof Story ? $s->title : '',
            [...$this->provider->fetch(Story::class, new DataQuery(sortField: 'writer.name', sortDirection: 'asc'))],
        );

        // The story with no writer is kept (LEFT, not INNER, join)...
        self::assertContains('Anonymous', $titles, 'A row with a null relation is kept (LEFT join).');
        // ...and among the rows that have a writer, Ada precedes Grace.
        $named = array_values(array_filter($titles, static fn (string $t): bool => 'Anonymous' !== $t));
        self::assertSame(['Looms', 'Engines'], $named);
    }

    public function testSearchAcrossARelationField(): void
    {
        $query = new DataQuery(search: 'Ada', searchableFields: ['writer.name']);

        self::assertSame(1, $this->provider->count(Story::class, $query));

        $rows = [...$this->provider->fetch(Story::class, $query)];
        self::assertCount(1, $rows);
        self::assertInstanceOf(Story::class, $rows[0]);
        self::assertSame('Looms', $rows[0]->title);
    }

    public function testFilterByRelationField(): void
    {
        $query = new DataQuery(filters: ['writer.name' => 'Grace']);

        $rows = [...$this->provider->fetch(Story::class, $query)];
        self::assertCount(1, $rows);
        self::assertInstanceOf(Story::class, $rows[0]);
        self::assertSame('Engines', $rows[0]->title);
    }

    public function testTheSameRelationIsJoinedOnceAcrossSearchSortAndFilter(): void
    {
        // Search, sort and filter all reference writer.name. If the join were
        // added three times, Doctrine would raise a duplicate-alias error; a clean
        // result proves it is joined once.
        $query = new DataQuery(
            search: 'a',
            searchableFields: ['writer.name'],
            sortField: 'writer.name',
            filters: ['writer.name' => 'Ada'],
        );

        $rows = [...$this->provider->fetch(Story::class, $query)];
        self::assertCount(1, $rows);
        self::assertInstanceOf(Story::class, $rows[0]);
        self::assertSame('Looms', $rows[0]->title);
    }
}
