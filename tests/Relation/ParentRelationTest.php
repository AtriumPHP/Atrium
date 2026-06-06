<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\ParentRelation;
use Atrium\Tests\Fixtures\Resource\PostRelResource;
use PHPUnit\Framework\TestCase;

final class ParentRelationTest extends TestCase
{
    public function testBuilderExposesParentClassRelationshipAndForeignKey(): void
    {
        $parent = ParentRelation::make(PostRelResource::class)
            ->relationship('comments')
            ->foreignKey('postId');

        self::assertSame(PostRelResource::class, $parent->getParentClass());
        self::assertSame('comments', $parent->getRelationship());
        self::assertSame('postId', $parent->getForeignKey());
        self::assertNull($parent->getRecordTitle());
    }

    public function testRecordTitleIsOptional(): void
    {
        $parent = ParentRelation::make(PostRelResource::class)
            ->relationship('comments')->foreignKey('postId')->recordTitle('title');

        self::assertSame('title', $parent->getRecordTitle());
    }

    public function testRelationshipAccessorThrowsWhenUnset(): void
    {
        $this->expectException(\LogicException::class);
        ParentRelation::make(PostRelResource::class)->getRelationship();
    }

    public function testForeignKeyAccessorThrowsWhenUnset(): void
    {
        $this->expectException(\LogicException::class);
        ParentRelation::make(PostRelResource::class)->getForeignKey();
    }
}
