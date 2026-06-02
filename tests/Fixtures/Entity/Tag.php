<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

/**
 * Plain test fixture entity (no Doctrine mapping needed for unit tests).
 */
final class Tag
{
    /**
     * @param list<string>          $labels
     * @param array<string, string> $meta
     */
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $slug = '',
        public bool $active = false,
        public array $labels = [],
        public array $meta = [],
        public ?string $kind = null,
    ) {
    }
}
