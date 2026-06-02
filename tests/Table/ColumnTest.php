<?php

declare(strict_types=1);

namespace Atrium\Tests\Table;

use Atrium\Table\Column;
use PHPUnit\Framework\TestCase;

final class ColumnTest extends TestCase
{
    public function testDefaults(): void
    {
        $column = Column::make('name');

        self::assertSame('name', $column->getName());
        self::assertFalse($column->isSortable());
        self::assertFalse($column->isSearchable());
    }

    public function testFluentConfiguration(): void
    {
        $column = Column::make('email')
            ->label('E-mail')
            ->sortable()
            ->searchable();

        self::assertSame('E-mail', $column->getLabel());
        self::assertTrue($column->isSortable());
        self::assertTrue($column->isSearchable());
    }

    public function testLabelIsHumanisedFromCamelCaseName(): void
    {
        self::assertSame('Created At', Column::make('createdAt')->getLabel());
    }

    public function testFlagsCanBeDisabledExplicitly(): void
    {
        $column = Column::make('id')->sortable(false)->searchable(false);

        self::assertFalse($column->isSortable());
        self::assertFalse($column->isSearchable());
    }
}
