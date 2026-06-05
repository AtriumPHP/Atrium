<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\ArrayRelationProvider;
use Atrium\DataProvider\DataQuery;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Entity\Comment;
use Atrium\Tests\Fixtures\Entity\Post;
use PHPUnit\Framework\TestCase;

final class ArrayRelationProviderTest extends TestCase
{
    private function descriptor(): RelationDescriptor
    {
        return new RelationDescriptor(
            name: 'comments',
            kind: RelationKind::OneToMany,
            childEntityClass: Comment::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'body',
            foreignKey: 'postId',
        );
    }

    public function testListRelatedReturnsOnlyChildrenOfTheParent(): void
    {
        $post = new Post(1, 'First');
        $other = new Post(2, 'Second');
        $provider = new ArrayRelationProvider([
            Comment::class => [
                new Comment(1, 'a', postId: 1),
                new Comment(2, 'b', postId: 2),
                new Comment(3, 'c', postId: 1),
            ],
        ]);

        $rows = [...$provider->listRelated($this->descriptor(), $post, new DataQuery())];

        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(Comment::class, $rows);
        self::assertSame([1, 3], array_map(static fn (Comment $c): int => $c->id, $rows));
        self::assertSame(2, $provider->countRelated($this->descriptor(), $post, new DataQuery()));
        self::assertCount(0, [...$provider->listRelated($this->descriptor(), $other, new DataQuery(offset: 5))]);
    }

    public function testListRelatedHonoursPaginationFromDataQuery(): void
    {
        $post = new Post(1, 'First');
        $comments = [];
        for ($i = 1; $i <= 5; ++$i) {
            $comments[] = new Comment($i, 'c'.$i, postId: 1);
        }
        $provider = new ArrayRelationProvider([Comment::class => $comments]);

        $rows = [...$provider->listRelated($this->descriptor(), $post, new DataQuery(offset: 2, limit: 2))];

        self::assertContainsOnlyInstancesOf(Comment::class, $rows);
        self::assertSame([3, 4], array_map(static fn (Comment $c): int => $c->id, $rows));
    }

    public function testListRelatedAppliesScopeFilters(): void
    {
        // A DataQuery filter (carrying the target resource's scopeQuery) must
        // exclude children that match the parent FK but fail the scope condition.
        $post = new Post(1, 'First');
        $provider = new ArrayRelationProvider([Comment::class => [
            new Comment(1, 'keep', postId: 1),
            new Comment(2, 'hide', postId: 1),
        ]]);
        $query = new DataQuery(filters: ['body' => 'keep']);

        $rows = [...$provider->listRelated($this->descriptor(), $post, $query)];

        self::assertCount(1, $rows);
        self::assertContainsOnlyInstancesOf(Comment::class, $rows);
        self::assertSame(1, $rows[0]->id);
        self::assertSame(1, $provider->countRelated($this->descriptor(), $post, $query));
    }

    public function testAssociateIsNotYetImplemented(): void
    {
        $this->expectException(\LogicException::class);
        (new ArrayRelationProvider())->associate($this->descriptor(), new Post(1, 'x'), new Comment(1, 'y', postId: null));
    }
}
