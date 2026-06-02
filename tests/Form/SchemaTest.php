<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testFieldsArePreservedInOrderAndLookupable(): void
    {
        $schema = (new Schema())->fields([
            TextField::make('name'),
            TextField::make('email')->email(),
        ]);

        self::assertCount(2, $schema->getFields());
        self::assertSame(['name', 'email'], array_map(
            static fn ($f): string => $f->getName(),
            $schema->getFields(),
        ));
        self::assertTrue($schema->hasField('email'));
        self::assertNotNull($schema->getField('email'));
        self::assertNull($schema->getField('missing'));
    }
}
