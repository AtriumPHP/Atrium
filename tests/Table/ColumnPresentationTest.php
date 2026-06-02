<?php

declare(strict_types=1);

namespace Atrium\Tests\Table;

use Atrium\Table\Column;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class ColumnPresentationTest extends TestCase
{
    private PropertyAccessorInterface $accessor;

    protected function setUp(): void
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function testDefaultsAndAlignment(): void
    {
        $column = Column::make('name');
        self::assertTrue($column->isVisible());
        self::assertSame('left', $column->getAlignment());
        self::assertNull($column->getWidth());
        self::assertFalse($column->isBoolean());
        self::assertFalse($column->isBadge());

        self::assertSame('right', Column::make('price')->alignRight()->getAlignment());
        self::assertSame('center', Column::make('flag')->alignCenter()->getAlignment());
        // An unknown alignment falls back to left.
        self::assertSame('left', Column::make('x')->alignment('sideways')->getAlignment());
        self::assertSame('8rem', Column::make('x')->width('8rem')->getWidth());
    }

    public function testVisibilityToggles(): void
    {
        self::assertFalse(Column::make('secret')->visible(false)->isVisible());
        self::assertFalse(Column::make('secret')->hidden()->isVisible());
        self::assertTrue(Column::make('secret')->hidden(false)->isVisible());
    }

    public function testBooleanCellCarriesState(): void
    {
        $record = new class {
            public bool $active = true;
            public bool $archived = false;
        };

        $on = Column::make('active')->boolean()->toCell($record, $this->accessor);
        self::assertTrue($on['boolean']);
        self::assertTrue($on['state']);

        $off = Column::make('archived')->boolean()->toCell($record, $this->accessor);
        self::assertFalse($off['state']);
    }

    public function testBadgeColourIsStaticOrResolvedFromValue(): void
    {
        $record = new class {
            public string $status = 'published';
        };

        $static = Column::make('status')->badge()->color('green')->toCell($record, $this->accessor);
        self::assertTrue($static['badge']);
        self::assertSame('green', $static['color']);
        self::assertSame('published', $static['value']);

        $dynamic = Column::make('status')->badge()->color(
            static fn (mixed $value): string => 'published' === $value ? 'green' : 'gray',
        )->toCell($record, $this->accessor);
        self::assertSame('green', $dynamic['color']);

        // No colour configured falls back to gray.
        self::assertSame('gray', Column::make('status')->badge()->toCell($record, $this->accessor)['color']);
    }
}
