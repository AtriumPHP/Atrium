<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

final class Task
{
    public function __construct(
        public int $id,
        public string $title,
        public ?int $projectId = null,
    ) {
    }
}
