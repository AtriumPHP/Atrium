<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Layout\Grid;
use Atrium\Layout\Section;
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

    public function testNestedLayoutFlattensToFieldsDepthFirst(): void
    {
        $schema = (new Schema())->components([
            Section::make('Identity')->schema([
                TextField::make('name'),
                Grid::make(2)->schema([
                    TextField::make('first'),
                    TextField::make('last'),
                ]),
            ]),
            TextField::make('email'),
        ]);

        // Rendering sees the top-level tree …
        self::assertCount(2, $schema->getComponents());

        // … while persistence/validation see the flattened leaves, in order.
        self::assertSame(
            ['name', 'first', 'last', 'email'],
            array_map(static fn ($f): string => $f->getName(), $schema->getFields()),
        );
        self::assertTrue($schema->hasField('last'));
        self::assertNotNull($schema->getField('email'));
    }
}
