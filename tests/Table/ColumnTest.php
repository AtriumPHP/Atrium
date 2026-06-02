<?php

declare(strict_types=1);

namespace Atrium\Tests\Table;

use Atrium\Table\Column;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

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

    public function testRelationColumnLabelHumanisesTheDottedPath(): void
    {
        self::assertSame('Author name', Column::make('author.name')->getLabel());
        self::assertSame('Author company name', Column::make('author.company.name')->getLabel());
    }

    public function testRelationColumnReadsANestedValue(): void
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $record = (object) ['author' => (object) ['name' => 'Ada']];

        self::assertSame('Ada', Column::make('author.name')->renderValue($record, $accessor));
    }

    public function testRelationColumnIsEmptyWhenTheRelationIsNull(): void
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $record = (object) ['author' => null];

        self::assertSame('', Column::make('author.name')->renderValue($record, $accessor));
    }
}
