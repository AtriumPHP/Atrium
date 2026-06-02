<?php

declare(strict_types=1);

namespace Atrium\Tests\Table;

use Atrium\Table\Filter\SelectFilter;
use Atrium\Table\Filter\TernaryFilter;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    public function testSelectFilterConditionsOnlyForKnownOptions(): void
    {
        $filter = SelectFilter::make('kind')->options(['fruit' => 'Fruit', 'tool' => 'Tool']);

        self::assertSame(['kind' => 'fruit'], $filter->conditions('fruit'));
        self::assertSame([], $filter->conditions(''), 'The empty choice clears the filter.');
        self::assertSame([], $filter->conditions('bogus'), 'An unknown value contributes no condition.');
    }

    public function testSelectFilterCanTargetAnotherField(): void
    {
        $filter = SelectFilter::make('category')->attribute('kind')->options(['fruit' => 'Fruit']);

        self::assertSame(['kind' => 'fruit'], $filter->conditions('fruit'));
    }

    public function testSelectFilterViewCarriesPlaceholderAndOptions(): void
    {
        $view = SelectFilter::make('kind')->options(['fruit' => 'Fruit'])->toView('fruit');

        self::assertSame('kind', $view['name']);
        self::assertSame('fruit', $view['value']);
        self::assertSame(['value' => '', 'label' => 'All'], $view['options'][0]);
        self::assertSame(['value' => 'fruit', 'label' => 'Fruit'], $view['options'][1]);
    }

    public function testTernaryFilterMapsToBooleanConditions(): void
    {
        $filter = TernaryFilter::make('active');

        self::assertSame(['active' => true], $filter->conditions('1'));
        self::assertSame(['active' => false], $filter->conditions('0'));
        self::assertSame([], $filter->conditions(''));
        self::assertSame([], $filter->conditions('anything-else'));
    }

    public function testTernaryFilterViewHasThreeOptions(): void
    {
        $view = TernaryFilter::make('active')->labels('On', 'Off')->toView('1');

        self::assertSame('1', $view['value']);
        self::assertCount(3, $view['options']);
        self::assertSame(['value' => '1', 'label' => 'On'], $view['options'][1]);
        self::assertSame(['value' => '0', 'label' => 'Off'], $view['options'][2]);
    }
}
