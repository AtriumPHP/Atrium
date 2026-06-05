<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\RelationKind;
use PHPUnit\Framework\TestCase;

final class RelationKindTest extends TestCase
{
    public function testCasesExist(): void
    {
        self::assertSame('one_to_many', RelationKind::OneToMany->value);
        self::assertSame('many_to_many', RelationKind::ManyToMany->value);
    }

    public function testUsesPivot(): void
    {
        self::assertFalse(RelationKind::OneToMany->usesPivot());
        self::assertTrue(RelationKind::ManyToMany->usesPivot());
    }
}
