<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

final class Project
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
