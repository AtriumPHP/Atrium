<?php

declare(strict_types=1);

namespace Atrium\Widget;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * A widget that renders a Chart.js chart (WGT-10).
 *
 * Subclass it, inject your data source, and return Chart.js `type`/`data`/
 * `options`. The framework assembles a Symfony UX {@see Chart} and renders it with
 * no JavaScript build step (the Stimulus controller ships through AssetMapper).
 */
abstract class ChartWidget extends Widget
{
    /**
     * Chart.js chart type — one of {@see Chart}'s `TYPE_*` values (`line`, `bar`,
     * `doughnut`, `pie`, `radar`, `polarArea`, …). Defaults to a line chart.
     */
    public function getType(): string
    {
        return Chart::TYPE_LINE;
    }

    /**
     * Chart.js data: `labels` plus one or more `datasets`.
     *
     * @return array<string, mixed>
     */
    abstract public function getData(): array;

    /**
     * Chart.js options (scales, plugins, …). Responsive defaults are merged in by
     * {@see buildChart()} unless you override the same keys.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [];
    }

    /**
     * Optional heading shown above the chart; null for none.
     */
    public function getHeading(): ?string
    {
        return null;
    }

    public function getView(): string
    {
        return '@Atrium/components/widget/chart.html.twig';
    }

    /**
     * Assemble the UX Chart.js {@see Chart} from this widget's type/data/options.
     *
     * @internal called by the host component during render
     */
    public function buildChart(ChartBuilderInterface $builder): Chart
    {
        $chart = $builder->createChart($this->getType());
        $chart->setData($this->getData());
        $chart->setOptions($this->getOptions() + $this->defaultOptions());

        return $chart;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultOptions(): array
    {
        // The card gives the canvas a fixed height, so let the chart fill it.
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
