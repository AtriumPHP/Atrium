<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine-mapped fixture entity for data-layer tests.
 */
#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $name = '',
        #[ORM\Column]
        public int $price = 0,
        // A unique natural key, used to exercise lookups by a custom identifier
        // field (a resource's getIdentifierField() other than the primary key).
        #[ORM\Column(unique: true, nullable: true)]
        public ?string $sku = null,
    ) {
    }
}
