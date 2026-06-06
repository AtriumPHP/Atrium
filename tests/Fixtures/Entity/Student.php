<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Many-to-many child fixture (Course ↔ Student via the course_student pivot). */
#[ORM\Entity]
#[ORM\Table(name: 'students')]
class Student
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
