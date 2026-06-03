<?php

declare(strict_types=1);

namespace Atrium\Tests\Dashboard;

use Atrium\Dashboard\DashboardConfiguration;
use Atrium\Layout\Grid;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\SalesChartWidget;
use Atrium\Widget\WidgetSlot;
use PHPUnit\Framework\TestCase;

final class DashboardConfigurationTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        self::assertSame([], (new DashboardConfiguration())->getComponents());
    }

    public function testWidgetsWrapsEachClassInASlot(): void
    {
        $components = (new DashboardConfiguration())
            ->widgets([CounterStatsWidget::class, SalesChartWidget::class])
            ->getComponents();

        self::assertCount(2, $components);
        self::assertContainsOnlyInstancesOf(WidgetSlot::class, $components);
        self::assertSame(CounterStatsWidget::class, $components[0]->getWidgetClass());
        self::assertSame(SalesChartWidget::class, $components[1]->getWidgetClass());
    }

    public function testSchemaKeepsTheGivenTree(): void
    {
        $grid = Grid::make(2)->schema([WidgetSlot::make(CounterStatsWidget::class)]);

        $components = (new DashboardConfiguration())->schema([$grid])->getComponents();

        self::assertSame([$grid], $components);
    }

    public function testWidgetsRejectsANonWidgetClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new DashboardConfiguration())->widgets([\stdClass::class]); // @phpstan-ignore argument.type
    }
}
