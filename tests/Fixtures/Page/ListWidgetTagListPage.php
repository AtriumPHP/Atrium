<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Page;

use Atrium\Layout\Grid;
use Atrium\Page\ListPage;
use Atrium\Page\ListWidgetsConfiguration;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\SalesChartWidget;
use Atrium\Widget\WidgetSlot;

/**
 * List page exercising the header/footer widget bands: a stats widget nested in a
 * Grid above the table, and a chart widget below it.
 */
final class ListWidgetTagListPage extends ListPage
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
