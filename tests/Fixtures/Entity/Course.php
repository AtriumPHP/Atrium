<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Many-to-many parent fixture (Course ↔ Student via the course_student pivot). */
#[ORM\Entity]
#[ORM\Table(name: 'courses')]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $title = '',
    ) {
    }
}
