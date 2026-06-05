<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Entity\Tag;
use PHPUnit\Framework\TestCase;

final class RelationDescriptorTest extends TestCase
{
    public function testHoldsResolvedValues(): void
    {
        $descriptor = new RelationDescriptor(
            name: 'comments',
            kind: RelationKind::OneToMany,
            childEntityClass: Tag::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'name',
            foreignKey: 'post_id',
        );

        self::assertSame('comments', $descriptor->name);
        self::assertSame(RelationKind::OneToMany, $descriptor->kind);
        self::assertSame(Tag::class, $descriptor->childEntityClass);
        self::assertSame('post_id', $descriptor->foreignKey);
        self::assertSame('name', $descriptor->recordTitleAttribute);
    }
}
