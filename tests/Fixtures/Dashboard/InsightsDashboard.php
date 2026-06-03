<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Dashboard;

use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardConfiguration;
use Atrium\Dashboard\WidgetSlot;
use Atrium\Layout\Grid;
use Atrium\Layout\Section;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\SalesChartWidget;

/**
 * A registered, accessible dashboard at `/admin/insights` that arranges a stats
 * widget and a chart widget inside a Section + 2-up Grid — exercising the layout
 * tree.
 */
final class InsightsDashboard extends Dashboard
{
    public function getTitle(): string
    {
        return 'Insights';
    }

    public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
    {
        return $dashboard->schema([
            Section::make('Key metrics')->schema([
                Grid::make(2)->schema([
                    WidgetSlot::make(CounterStatsWidget::class),
                    WidgetSlot::make(SalesChartWidget::class)->columnSpan(1),
                ]),
            ]),
        ]);
    }

    public function getNavigationSort(): int
    {
        return 10;
    }
}
