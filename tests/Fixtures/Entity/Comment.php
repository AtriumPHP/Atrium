<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

/** Plain child fixture (one-to-many: Comment.postId → Post). */
final class Comment
{
    public function __construct(
        public int $id = 0,
        public string $body = '',
        public ?int $postId = null,
    ) {
    }
}
