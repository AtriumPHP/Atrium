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
 */
final class SampleData
{
    public static function provider(): ArrayDataProvider
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

        return new ArrayDataProvider([
            Tag::class => $tags,
            // Parent records for the relation-manager functional test.
            Post::class => [
                new Post(1, 'First post'),
                new Post(2, 'Second post'),
            ],
        ]);
    }

    public static function relationProvider(): ArrayRelationProvider
    {
        return new ArrayRelationProvider([
            // Comments belong to a Post via Comment.postId (one-to-many).
            Comment::class => [
                new Comment(1, 'Great post', postId: 1),
                new Comment(2, 'Thanks for sharing', postId: 1),
                new Comment(3, 'On the other post', postId: 2),
            ],
        ]);
    }
}
