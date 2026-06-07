<?php

declare(strict_types=1);

namespace Atrium\Tests\DataProvider;

use Atrium\DataProvider\ArrayRelationProvider;
use Atrium\DataProvider\DataQuery;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationKind;
use Atrium\Tests\Fixtures\Entity\Comment;
use Atrium\Tests\Fixtures\Entity\Post;
use Atrium\Tests\Fixtures\Entity\Tag;
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

    private function pivotDescriptor(): RelationDescriptor
    {
        return new RelationDescriptor(
            name: 'tags',
            kind: RelationKind::ManyToMany,
            childEntityClass: Tag::class,
            childIdField: 'id',
            parentIdField: 'id',
            recordTitleAttribute: 'name',
            pivotTable: 'post_tag',
            pivotParentKey: 'post_id',
            pivotRelatedKey: 'tag_id',
            pivotColumns: ['note'],
        );
    }

    public function testManyToManyListRelatedJoinsThroughThePivot(): void
    {
        $post = new Post(1, 'First');
        $provider = new ArrayRelationProvider(
            [Tag::class => [new Tag(1, 'A', 'a'), new Tag(2, 'B', 'b'), new Tag(3, 'C', 'c')]],
            pivots: ['post_tag' => [
                ['parent' => 1, 'related' => 1, 'columns' => []],
                ['parent' => 1, 'related' => 3, 'columns' => []],
                ['parent' => 2, 'related' => 2, 'columns' => []],
            ]],
        );

        $rows = [...$provider->listRelated($this->pivotDescriptor(), $post, new DataQuery())];
        self::assertContainsOnlyInstancesOf(Tag::class, $rows);
        self::assertSame([1, 3], array_map(static fn (Tag $t): int => $t->id, $rows));
        self::assertSame(2, $provider->countRelated($this->pivotDescriptor(), $post, new DataQuery()));
    }

    public function testManyToManyListLinkableExcludesAlreadyLinked(): void
    {
        $post = new Post(1, 'First');
        $provider = new ArrayRelationProvider(
            [Tag::class => [new Tag(1, 'A', 'a'), new Tag(2, 'B', 'b'), new Tag(3, 'C', 'c')]],
            pivots: ['post_tag' => [['parent' => 1, 'related' => 1, 'columns' => []]]],
        );

        $rows = [...$provider->listLinkable($this->pivotDescriptor(), $post, new DataQuery())];
        self::assertContainsOnlyInstancesOf(Tag::class, $rows);
        self::assertSame([2, 3], array_map(static fn (Tag $t): int => $t->id, $rows));
        self::assertSame(2, $provider->countLinkable($this->pivotDescriptor(), $post, new DataQuery()));
    }

    public function testManyToManyAttachAddsPivotRowAndDetachRemovesIt(): void
    {
        $post = new Post(1, 'First');
        $tag = new Tag(5, 'New', 'new');
        $provider = new ArrayRelationProvider([Tag::class => [$tag]], pivots: ['post_tag' => []]);

        $provider->attach($this->pivotDescriptor(), $post, $tag, ['note' => 'hi']);
        $rows = [...$provider->listRelated($this->pivotDescriptor(), $post, new DataQuery())];
        self::assertContainsOnlyInstancesOf(Tag::class, $rows);
        self::assertSame([5], array_map(static fn (Tag $t): int => $t->id, $rows));

        $provider->detach($this->pivotDescriptor(), $post, $tag);
        self::assertCount(0, [...$provider->listRelated($this->pivotDescriptor(), $post, new DataQuery())]);
    }

    public function testManyToManyAttachIsIdempotent(): void
    {
        // Re-attaching an already-linked pair must not create a duplicate pivot row
        // (parity with a unique pivot key on the Doctrine side).
        $post = new Post(1, 'First');
        $tag = new Tag(5, 'New', 'new');
        $provider = new ArrayRelationProvider([Tag::class => [$tag]], pivots: ['post_tag' => []]);

        $provider->attach($this->pivotDescriptor(), $post, $tag);
        $provider->attach($this->pivotDescriptor(), $post, $tag);

        self::assertSame(1, $provider->countRelated($this->pivotDescriptor(), $post, new DataQuery()));
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

    public function testAssociateSetsForeignKeyAndDissociateClearsIt(): void
    {
        $parent = new Post(1, 'First post');
        $orphan = new Comment(9, 'Orphan', postId: null);
        $provider = new ArrayRelationProvider([Comment::class => [$orphan]]);

        $provider->associate($this->descriptor(), $parent, $orphan);
        self::assertSame(1, $orphan->postId);

        $provider->dissociate($this->descriptor(), $parent, $orphan);
        self::assertNull($orphan->postId);
    }

    public function testListLinkableReturnsOnlyUnlinkedChildren(): void
    {
        $parent = new Post(1, 'First post');
        $provider = new ArrayRelationProvider([
            Comment::class => [
                new Comment(1, 'linked', postId: 1),
                new Comment(2, 'free a', postId: null),
                new Comment(3, 'free b', postId: null),
            ],
        ]);

        $rows = [...$provider->listLinkable($this->descriptor(), $parent, new DataQuery())];

        self::assertCount(2, $rows);
        self::assertContainsOnlyInstancesOf(Comment::class, $rows);
        self::assertSame(2, $provider->countLinkable($this->descriptor(), $parent, new DataQuery()));
    }
}
