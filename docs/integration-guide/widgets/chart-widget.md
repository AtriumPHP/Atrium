# Chart widget

> A Chart.js chart — line, bar, doughnut and more — configured in PHP and rendered
> with Symfony UX Chart.js, with **no JavaScript build step**.

## When to use

Use a `ChartWidget` to visualise a series: sales per month, signups per day, a
status breakdown. You return Chart.js `type`/`data`/`options`; the framework
assembles the chart and ships its Stimulus controller through AssetMapper.

## Requirements

Chart widgets require **`symfony/ux-chartjs`** (a dependency of the widgets
layer). With AssetMapper it needs no build step; its controller is registered
automatically on install.

## Example

```php
namespace App\Widget;

use App\Repository\OrderRepository;
use Atrium\Widget\ChartWidget;
use Symfony\UX\Chartjs\Model\Chart;

final class SalesChart extends ChartWidget
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function getHeading(): ?string
    {
        return 'Sales per month';
    }

    public function getType(): string
    {
        return Chart::TYPE_LINE; // 'line' | 'bar' | 'doughnut' | 'pie' | 'radar' | …
    }

    public function getData(): array
    {
        $byMonth = $this->orders->salesByMonth(); // ['Jan' => 1200, 'Feb' => 1900, …]

        return [
            'labels' => array_keys($byMonth),
            'datasets' => [[
                'label' => 'Sales',
                'data' => array_values($byMonth),
            ]],
        ];
    }
}
```

The integrator computes the aggregate (here `salesByMonth()` is your own
repository method — a Doctrine `GROUP BY`, a cached query, whatever fits); the
framework adds no aggregation API.

## `ChartWidget` API

| Method | Description |
| --- | --- |
| `getData(): array` | **Required.** Chart.js data: `labels` plus one or more `datasets`. |
| `getType(): string` | Chart type — a `Chart::TYPE_*` value. Default `line`. |
| `getOptions(): array` | Chart.js options (scales, plugins…). Merged under responsive defaults; `[]` keeps them. |
| `getHeading(): ?string` | Optional heading above the chart; `null` for none. |

It also inherits `canView()` and `getPollingInterval()` from
[`Widget`](overview.md#shared-widget-api); width is set by the placing
[`WidgetSlot`](dashboards.md#widgetslot-api). The card gives the canvas a fixed
height; the defaults (`responsive`, `maintainAspectRatio: false`) make the chart
fill it.

## See also

- [Widgets overview](overview.md) — the shared widget contract
- [Stats widget](stats-widget.md) — the other built-in widget family
- [Dashboards](dashboards.md) — compose a chart onto a page
