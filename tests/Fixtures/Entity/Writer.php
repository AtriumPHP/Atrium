<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine-mapped fixture entity on the "one" side of a relation, used to test
 * relation columns (dotted field names resolved to joins).
 */
#[ORM\Entity]
#[ORM\Table(name: 'writers')]
class Writer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $name = '',
    ) {
    }
}
