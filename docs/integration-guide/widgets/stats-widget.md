# Stats widget

> A row of stat cards — a label, a big value, and an optional trend line — laid
> out in a responsive grid.

## When to use

Use a `StatsWidget` for headline numbers: totals, counts, this-month figures,
conversion rates. Each number is a `Stat` card; the widget arranges them in a
responsive grid. How wide the widget sits is set by its placement (a
[`WidgetSlot`](dashboards.md#widgetslot-api)), not the widget.

## Example

```php
namespace App\Widget;

use App\Repository\OrderRepository;
use Atrium\Widget\Stat;
use Atrium\Widget\StatsWidget;

final class SalesStats extends StatsWidget
{
    // A widget is a service — inject whatever computes the numbers.
    public function __construct(private OrderRepository $orders)
    {
    }

    public function getStats(): array
    {
        return [
            Stat::make('Revenue (30d)', '$'.number_format($this->orders->revenueLast(30)))
                ->description('+12% vs prior')
                ->descriptionIcon('arrow-trending-up')
                ->color('success'),
            Stat::make('Orders', $this->orders->countLast(30)),
            Stat::make('Refunds', $this->orders->refundsLast(30))
                ->color('danger'),
        ];
    }

    // Optional: refresh every 30 seconds.
    public function getPollingInterval(): ?string
    {
        return '30s';
    }
}
```

Render it on a [dashboard](dashboards.md) (`getWidgets()`) or
[embed it](embedding.md) directly.

## `StatsWidget` API

| Method | Description |
| --- | --- |
| `getStats(): array` | **Required.** Return a `list<Stat>`, left to right. |
| `getColumns(): int` | Columns for the inner stat grid at `lg`+ (1 on mobile, 2 at `sm`+). Default `3`. |

It also inherits `canView()` and `getPollingInterval()` from
[`Widget`](overview.md#shared-widget-api). Width is set by the placing
[`WidgetSlot`](dashboards.md#widgetslot-api).

## `Stat` API

A fluent value builder, in the same style as [`Column`](../tables/columns.md).

| Method | Description |
| --- | --- |
| `make(string $label, string\|int\|float $value): self` | Create a stat; the value is cast to a string. |
| `description(string): self` | A sub-line under the value (e.g. a trend). |
| `descriptionIcon(string): self` | An icon next to the description. |
| `color(string): self` | Description colour: a palette name (`primary`, `gray`, `green`, `red`, `amber`, `sky`) or an intent alias (`success`, `danger`, `warning`, `info`). Default `gray`. |
| `url(string): self` | Make the whole card a link. |

## See also

- [Widgets overview](overview.md) — the shared widget contract and data philosophy
- [Chart widget](chart-widget.md) — the other built-in widget family
- [Dashboards](dashboards.md) — compose stats onto a page
