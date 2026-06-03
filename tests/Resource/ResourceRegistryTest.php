<?php

declare(strict_types=1);

namespace Atrium\Tests\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Resource\TagResource;
use PHPUnit\Framework\TestCase;

final class ResourceRegistryTest extends TestCase
{
    public function testIndexesBySlugAndClass(): void
    {
        $resource = new TagResource();
        $registry = new ResourceRegistry([$resource]);

        self::assertTrue($registry->hasSlug('tag'));
        self::assertSame($resource, $registry->getBySlug('tag'));
        self::assertSame($resource, $registry->getByClass(TagResource::class));
        self::assertSame([$resource], $registry->all());
    }

    public function testSlugIsDerivedFromEntityShortName(): void
    {
        self::assertSame('tag', (new TagResource())->getSlug());
        self::assertSame(Tag::class, (new TagResource())->getEntityClass());
    }

    public function testUnknownSlugThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ResourceRegistry())->getBySlug('missing');
    }

    public function testDuplicateSlugThrows(): void
    {
        $this->expectException(\LogicException::class);

        new ResourceRegistry([new TagResource(), new TagResource()]);
    }

    public function testTableDefaultsToNoColumnsWithEditAndCreateActions(): void
    {
        $resource = new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Tag::class;
            }
        };

        $table = $resource->table(TableConfiguration::make());

        self::assertSame([], $table->getColumns());
        // A fresh table carries the framework defaults.
        self::assertCount(1, $table->getRecordActions());
        self::assertSame([], $table->getBulkActions());
    }
}
