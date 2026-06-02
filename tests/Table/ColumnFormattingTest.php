<?php

declare(strict_types=1);

namespace Atrium\Tests\Table;

use Atrium\Table\Column;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class ColumnFormattingTest extends TestCase
{
    private PropertyAccessorInterface $accessor;

    protected function setUp(): void
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    public function testRendersScalarValue(): void
    {
        $record = new class {
            public string $name = 'Acme';
        };

        self::assertSame('Acme', Column::make('name')->renderValue($record, $this->accessor));
    }

    public function testRendersNullAsEmptyAndBooleanAsYesNo(): void
    {
        $record = new class {
            public ?string $missing = null;
            public bool $active = true;
        };

        self::assertSame('', Column::make('missing')->renderValue($record, $this->accessor));
        self::assertSame('Yes', Column::make('active')->renderValue($record, $this->accessor));
    }

    public function testRendersDateTimeBackedEnumAndArray(): void
    {
        $record = new class {
            public \DateTimeImmutable $createdAt;
            public Suit $suit = Suit::Hearts;
            /** @var list<string> */
            public array $tags = ['a', 'b'];

            public function __construct()
            {
                $this->createdAt = new \DateTimeImmutable('2026-01-02 03:04:05');
            }
        };

        self::assertSame('2026-01-02 03:04', Column::make('createdAt')->renderValue($record, $this->accessor));
        self::assertSame('H', Column::make('suit')->renderValue($record, $this->accessor));
        self::assertSame('a, b', Column::make('tags')->renderValue($record, $this->accessor));
    }

    public function testCustomFormatterReceivesValueAndRecord(): void
    {
        $column = Column::make('price')->formatStateUsing(
            static function (mixed $value, object $row): string {
                if (!$row instanceof PricedProduct || !\is_int($value)) {
                    return '';
                }

                return \sprintf('%s %.2f', $row->currency, $value / 100);
            },
        );

        self::assertSame('USD 15.00', $column->renderValue(new PricedProduct(), $this->accessor));
    }

    public function testUnreadablePropertyRendersAsEmpty(): void
    {
        $record = new class {
            public string $name = 'x';
        };

        self::assertSame('', Column::make('nonexistent')->renderValue($record, $this->accessor));
    }
}

enum Suit: string
{
    case Hearts = 'H';
    case Spades = 'S';
}

final class PricedProduct
{
    public int $price = 1500;

    public string $currency = 'USD';
}
