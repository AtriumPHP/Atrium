<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DoctrineDataProvider;
use Atrium\DataProvider\DoctrineDataWriter;
use Atrium\Tests\Fixtures\Doctrine\EntityManagerFactory;
use Atrium\Tests\Fixtures\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class DoctrineDataWriterTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineDataWriter $writer;

    private DoctrineDataProvider $provider;

    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::create();
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(Product::class),
        ]);

        $this->writer = new DoctrineDataWriter($this->entityManager);
        $this->provider = new DoctrineDataProvider($this->entityManager);
    }

    public function testCreatePersistsTheEntity(): void
    {
        $this->writer->create(new Product('Widget', 999));

        self::assertSame(1, $this->provider->count(Product::class, new DataQuery()));
    }

    public function testUpdatePersistsChanges(): void
    {
        $product = new Product('Widget', 999);
        $this->writer->create($product);

        $product->price = 1499;
        $this->writer->update($product);
        $this->entityManager->clear();

        $reloaded = [...$this->provider->fetch(Product::class, new DataQuery())][0];
        self::assertInstanceOf(Product::class, $reloaded);
        self::assertSame(1499, $reloaded->price);
    }

    public function testDeleteRemovesTheEntity(): void
    {
        $product = new Product('Widget', 999);
        $this->writer->create($product);

        $this->writer->delete($product);

        self::assertSame(0, $this->provider->count(Product::class, new DataQuery()));
    }
}
