<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine-mapped fixture entity on the "many" side, with a to-one relation to
 * {@see Writer}. Exercises relation columns like `writer.name`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stories')]
class Story
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $title = '',
        #[ORM\ManyToOne(targetEntity: Writer::class)]
        public ?Writer $writer = null,
    ) {
    }
}
