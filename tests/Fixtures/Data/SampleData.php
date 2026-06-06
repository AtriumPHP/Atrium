<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Data;

use Atrium\DataProvider\ArrayDataProvider;
use Atrium\DataProvider\ArrayRelationProvider;
use Atrium\Tests\Fixtures\Entity\Comment;
use Atrium\Tests\Fixtures\Entity\Post;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Seeds the in-memory adapters with sample records for the functional tests, so
 * the panel can be exercised without a database.
 *
 * Registered as a service (one instance per container boot), so {@see provider()}
 * and {@see relationProvider()} share the *same* fixture objects — mirroring a
 * single backend where reads and relation queries see one record store. A fresh
 * instance per boot keeps tests isolated.
 *
 * Note: the {@see \Atrium\DataProvider\ArrayDataWriter} is a *separate* store, so
 * a create/delete through the writer is observed via the writer's own `created`/
 * `deleted` lists (and a dissociate via the shared object's mutated FK), not by
 * re-reading the provider. In production a single Doctrine backend unifies them.
 */
final class SampleData
{
    /** @var list<Tag> */
    private array $tags;

    /** @var list<Post> */
    private array $posts;

    /** @var list<Comment> */
    private array $comments;

    public function __construct()
    {
        $kinds = ['fruit', 'tool', 'animal'];
        $tags = [];
        for ($i = 1; $i <= 12; ++$i) {
            $tags[] = new Tag(
                id: $i,
                name: \sprintf('Tag %02d', $i),
                slug: \sprintf('tag-%02d', $i),
                active: 0 === $i % 2,
                kind: $kinds[($i - 1) % 3],
            );
        }
        $this->tags = $tags;

        // Parent records for the relation-manager functional tests.
        $this->posts = [
            new Post(1, 'First post'),
            new Post(2, 'Second post'),
        ];

        // Comments belong to a Post via Comment.postId (one-to-many). Ids 100/101
        // have no parent yet, so they are the Associate-picker candidates.
        $this->comments = [
            new Comment(1, 'Great post', postId: 1),
            new Comment(2, 'Thanks for sharing', postId: 1),
            new Comment(3, 'On the other post', postId: 2),
            new Comment(100, 'Unassigned A', postId: null),
            new Comment(101, 'Unassigned B', postId: null),
        ];
    }

    public function provider(): ArrayDataProvider
    {
        return new ArrayDataProvider([
            Tag::class => $this->tags,
            Post::class => $this->posts,
            Comment::class => $this->comments,
        ]);
    }

    public function relationProvider(): ArrayRelationProvider
    {
        return new ArrayRelationProvider([
            Comment::class => $this->comments,
        ]);
    }
}
