<?php

declare(strict_types=1);

namespace Atrium\Tests\Page;

use Atrium\Layout\Grid;
use Atrium\Page\ListWidgetsConfiguration;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\SalesChartWidget;
use Atrium\Widget\WidgetLayoutConfiguration;
use Atrium\Widget\WidgetSlot;
use PHPUnit\Framework\TestCase;

final class ListWidgetsConfigurationTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        self::assertSame([], (new ListWidgetsConfiguration())->getComponents());
    }

    public function testIsAWidgetLayout(): void
    {
        // A distinct type, but shares the layout behaviour with the dashboard's.
        self::assertInstanceOf(WidgetLayoutConfiguration::class, new ListWidgetsConfiguration());
    }

    public function testWidgetsWrapsEachClassInASlot(): void
    {
        $components = (new ListWidgetsConfiguration())
            ->widgets([CounterStatsWidget::class, SalesChartWidget::class])
            ->getComponents();

        self::assertCount(2, $components);
        self::assertContainsOnlyInstancesOf(WidgetSlot::class, $components);
        self::assertSame(CounterStatsWidget::class, $components[0]->getWidgetClass());
    }

    public function testApplyContextReachesEverySlotIncludingNestedOnes(): void
    {
        $topLevel = WidgetSlot::make(SalesChartWidget::class);
        $nested = WidgetSlot::make(CounterStatsWidget::class);

        $config = (new ListWidgetsConfiguration())->schema([
            $topLevel,
            Grid::make(2)->schema([$nested]),
        ]);

        $config->applyContext(['resource' => 'product', 'pathPrefix' => '/admin']);

        self::assertSame(['resource' => 'product', 'pathPrefix' => '/admin'], $topLevel->getParams());
        self::assertSame(['resource' => 'product', 'pathPrefix' => '/admin'], $nested->getParams());
    }
}
