<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Widget;

use Atrium\Widget\ChartWidget;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * A small bar chart fixture used to exercise {@see ChartWidget} rendering.
 */
final class SalesChartWidget extends ChartWidget
{
    public function getHeading(): string
    {
        return 'Sales per month';
    }

    public function getType(): string
    {
        return Chart::TYPE_BAR;
    }

    public function getData(): array
    {
        return [
            'labels' => ['Jan', 'Feb', 'Mar'],
            'datasets' => [[
                'label' => 'Sales',
                'data' => [120, 190, 300],
            ]],
        ];
    }
}
