<?php

declare(strict_types=1);

namespace Atrium\Tests\Widget;

use Atrium\Widget\ChartWidget;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class ChartWidgetTest extends TestCase
{
    public function testDefaults(): void
    {
        $widget = $this->lineWidget();

        self::assertSame(Chart::TYPE_LINE, $widget->getType());
        self::assertSame([], $widget->getOptions());
        self::assertNull($widget->getHeading());
        self::assertSame('@Atrium/components/widget/chart.html.twig', $widget->getView());
        self::assertSame('col-span-full', $widget->getColumnSpanClass());
    }

    public function testBuildChartSetsTypeDataAndMergesResponsiveDefaults(): void
    {
        $chart = $this->lineWidget()->buildChart($this->builder());

        self::assertSame(Chart::TYPE_LINE, $chart->getType());
        self::assertSame(['labels' => ['A'], 'datasets' => [['data' => [1]]]], $chart->getData());
        self::assertSame(
            ['responsive' => true, 'maintainAspectRatio' => false],
            $chart->getOptions(),
        );
    }

    public function testWidgetOptionsOverrideDefaults(): void
    {
        $widget = new class extends ChartWidget {
            public function getData(): array
            {
                return [];
            }

            public function getOptions(): array
            {
                return ['responsive' => false, 'scales' => ['y' => ['min' => 0]]];
            }
        };

        $options = $widget->buildChart($this->builder())->getOptions();

        self::assertFalse($options['responsive']);
        self::assertSame(['y' => ['min' => 0]], $options['scales']);
        self::assertFalse($options['maintainAspectRatio']);
    }

    private function lineWidget(): ChartWidget
    {
        return new class extends ChartWidget {
            public function getData(): array
            {
                return ['labels' => ['A'], 'datasets' => [['data' => [1]]]];
            }
        };
    }

    private function builder(): ChartBuilderInterface
    {
        return new class implements ChartBuilderInterface {
            public function createChart(string $type): Chart
            {
                return new Chart($type);
            }
        };
    }
}
