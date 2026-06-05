<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

/** Plain parent fixture for relation tests. */
final class Post
{
    public function __construct(
        public int $id = 0,
        public string $title = '',
    ) {
    }
}
