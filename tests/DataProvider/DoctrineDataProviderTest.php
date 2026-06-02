<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DoctrineDataProvider;
use Atrium\Tests\Fixtures\Doctrine\EntityManagerFactory;
use Atrium\Tests\Fixtures\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class DoctrineDataProviderTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineDataProvider $provider;

    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::create();

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([$this->entityManager->getClassMetadata(Product::class)]);

        $this->provider = new DoctrineDataProvider($this->entityManager);
    }

    public function testCountReflectsPersistedRows(): void
    {
        self::assertSame(0, $this->provider->count(Product::class, new DataQuery()));

        $this->seed(30);

        self::assertSame(30, $this->provider->count(Product::class, new DataQuery()));
    }

    public function testFetchReturnsEntityInstances(): void
    {
        $this->seed(3);

        $products = [...$this->provider->fetch(Product::class, new DataQuery())];

        self::assertCount(3, $products);
        self::assertContainsOnlyInstancesOf(Product::class, $products);
    }

    public function testFetchAppliesDefaultLimit(): void
    {
        $this->seed(30);

        self::assertCount(25, [...$this->provider->fetch(Product::class, new DataQuery())]);
    }

    public function testFetchPaginatesWithOffsetAndLimit(): void
    {
        $this->seed(30);

        self::assertCount(10, [...$this->provider->fetch(Product::class, new DataQuery(offset: 0, limit: 10))]);
        self::assertCount(5, [...$this->provider->fetch(Product::class, new DataQuery(offset: 25, limit: 10))]);
        self::assertCount(0, [...$this->provider->fetch(Product::class, new DataQuery(offset: 30, limit: 10))]);
    }

    public function testSearchBindsTheTermAsAParameterAndMatchesFields(): void
    {
        $this->seed(30);

        $exact = new DataQuery(search: 'Product 07', searchableFields: ['name']);
        self::assertSame(1, $this->provider->count(Product::class, $exact));

        $partial = new DataQuery(search: 'Product 1', searchableFields: ['name']);
        self::assertSame(10, $this->provider->count(Product::class, $partial));
        self::assertCount(10, [...$this->provider->fetch(Product::class, $partial)]);
    }

    public function testSortByFieldAscendingAndDescending(): void
    {
        $this->seed(30);

        $highest = [...$this->provider->fetch(Product::class, new DataQuery(sortField: 'price', sortDirection: 'desc', limit: 1))];
        $lowest = [...$this->provider->fetch(Product::class, new DataQuery(sortField: 'price', sortDirection: 'asc', limit: 1))];

        self::assertInstanceOf(Product::class, $highest[0]);
        self::assertInstanceOf(Product::class, $lowest[0]);
        self::assertSame(3000, $highest[0]->price);
        self::assertSame(100, $lowest[0]->price);
    }

    private function seed(int $count): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->entityManager->persist(new Product(\sprintf('Product %02d', $i), $i * 100));
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
