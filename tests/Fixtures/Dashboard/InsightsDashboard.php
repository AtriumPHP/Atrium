<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Dashboard;

use Atrium\Dashboard\Dashboard;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\SalesChartWidget;

/**
 * A registered, accessible dashboard at `/admin/insights` composing a stats
 * widget and a chart widget.
 */
final class InsightsDashboard extends Dashboard
{
    public function getTitle(): string
    {
        return 'Insights';
    }

    public function getWidgets(): array
    {
        return [CounterStatsWidget::class, SalesChartWidget::class];
    }

    public function getNavigationSort(): int
    {
        return 10;
    }
}
