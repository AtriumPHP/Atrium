<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\Relation;
use Atrium\Relation\RelationResolver;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Resource\TagResource;
use PHPUnit\Framework\TestCase;

final class RelationResolverTest extends TestCase
{
    public function testResolvesTargetEntityAndDefaults(): void
    {
        $registry = new ResourceRegistry([new TagResource()]);
        $resolver = new RelationResolver($registry);

        $descriptor = $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class)->foreignKey('post_id')->recordTitle('name'),
            parentIdField: 'id',
        );

        self::assertSame(Tag::class, $descriptor->childEntityClass);
        self::assertSame('id', $descriptor->childIdField);
        self::assertSame('id', $descriptor->parentIdField);
        self::assertSame('name', $descriptor->recordTitleAttribute);
        self::assertSame('post_id', $descriptor->foreignKey);
    }

    public function testRecordTitleDefaultsToTargetIdentifierField(): void
    {
        $resolver = new RelationResolver(new ResourceRegistry([new TagResource()]));

        $descriptor = $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class)->foreignKey('post_id'),
            parentIdField: 'id',
        );

        self::assertSame('id', $descriptor->recordTitleAttribute);
    }

    public function testOneToManyWithoutForeignKeyThrows(): void
    {
        $resolver = new RelationResolver(new ResourceRegistry([new TagResource()]));

        $this->expectException(\InvalidArgumentException::class);
        $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class),
            parentIdField: 'id',
        );
    }

    public function testUnregisteredTargetThrows(): void
    {
        $resolver = new RelationResolver(new ResourceRegistry([]));

        $this->expectException(\InvalidArgumentException::class);
        $resolver->resolve(
            Relation::make('tags')->oneToMany(TagResource::class)->foreignKey('post_id'),
            parentIdField: 'id',
        );
    }
}
