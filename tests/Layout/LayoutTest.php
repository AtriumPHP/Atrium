<?php

declare(strict_types=1);

namespace Atrium\Tests\Layout;

use Atrium\Form\Field\TextField;
use Atrium\Layout\Fieldset;
use Atrium\Layout\Grid;
use Atrium\Layout\Section;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    public function testGridColumnsAreResponsive(): void
    {
        self::assertSame('grid-cols-1', Grid::make(1)->getGridClass());
        self::assertSame('grid-cols-1 lg:grid-cols-2', Grid::make()->getGridClass());
        self::assertSame('grid-cols-1 lg:grid-cols-3', Grid::make(3)->getGridClass());
        self::assertSame(
            'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
            Grid::make(['md' => 2, 'xl' => 4])->getGridClass(),
        );
    }

    public function testColumnSpanClasses(): void
    {
        self::assertSame('', TextField::make('a')->getColumnSpanClass());
        self::assertSame('lg:col-span-2', TextField::make('a')->columnSpan(2)->getColumnSpanClass());
        self::assertSame('col-span-full', TextField::make('a')->columnSpanFull()->getColumnSpanClass());
    }

    public function testSectionFlags(): void
    {
        $section = Section::make('Billing')
            ->description('How we charge you.')
            ->columns(2)
            ->compact();

        self::assertSame('Billing', $section->getHeading());
        self::assertSame('How we charge you.', $section->getDescription());
        self::assertSame('grid-cols-1 lg:grid-cols-2', $section->getGridClass());
        self::assertTrue($section->isCompact());
        self::assertFalse($section->isCollapsible());
    }

    public function testCollapsedImpliesCollapsible(): void
    {
        $section = Section::make('Advanced')->collapsed();

        self::assertTrue($section->isCollapsed());
        self::assertTrue($section->isCollapsible());
    }

    public function testFieldsetDefaultsToTwoColumnsAndIsContained(): void
    {
        $fieldset = Fieldset::make('Address');

        self::assertSame('Address', $fieldset->getLabel());
        self::assertSame('grid-cols-1 lg:grid-cols-2', $fieldset->getGridClass());
        self::assertTrue($fieldset->isContained());
        self::assertFalse($fieldset->contained(false)->isContained());
    }

    public function testLayoutExposesItsChildren(): void
    {
        $grid = Grid::make()->schema([
            TextField::make('a'),
            TextField::make('b'),
        ]);

        self::assertCount(2, $grid->getChildComponents());
        self::assertSame('@Atrium/components/layout/grid.html.twig', $grid->getTemplate());
    }
}
