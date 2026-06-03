<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Page;

use Atrium\Layout\Grid;
use Atrium\Page\ListWidgetsConfiguration;
use Atrium\Page\ViewPage;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\SalesChartWidget;
use Atrium\Widget\WidgetSlot;

/**
 * View page exercising the record-scoped header/footer widget bands (VIEW-16): a
 * stats widget nested in a Grid above the entries, and a chart widget below them.
 */
final class ViewWidgetTagViewPage extends ViewPage
{
    public function headerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config->schema([
            Grid::make(2)->schema([
                WidgetSlot::make(CounterStatsWidget::class),
            ]),
        ]);
    }

    public function footerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config->widgets([SalesChartWidget::class]);
    }
}
