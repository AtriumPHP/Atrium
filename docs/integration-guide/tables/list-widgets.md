# List widgets

> Render [widget](../widgets/overview.md) bands above and below a resource's list
> table — summary stats, a chart — declared on the list page and composed like a
> dashboard.

## When to use

A list screen often wants a band of summary widgets over the table ("Products",
"Revenue this month") and sometimes a chart beneath it. Declare them on a
[`ListPage`](../pages/overview.md) subclass via `headerWidgets()` (above the table)
and `footerWidgets()` (below it), then point the resource's `pages()['index']` at
that page.

They are **page-owned presentation** — the same place the list's heading and
header actions live. The table itself (columns, filters, record actions) stays on
the resource's [`table()`](table-configuration.md); data config on the resource,
per-screen presentation on the page.

```php
namespace App\Admin\Pages;

use App\Widget\ProductStatsWidget;
use App\Widget\ProductsByCategoryChart;
use Atrium\Layout\Grid;
use Atrium\Page\ListPage;
use Atrium\Page\ListWidgetsConfiguration;
use Atrium\Widget\WidgetSlot;

final class ProductList extends ListPage
{
    public function headerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        // A flat stack:
        //   return $config->widgets([ProductStatsWidget::class]);
        // …or a composed layout (Grid/Section/…), exactly like a dashboard:
        return $config->schema([
            Grid::make(1)->schema([
                WidgetSlot::make(ProductStatsWidget::class),
            ]),
        ]);
    }

    public function footerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config->widgets([ProductsByCategoryChart::class]);
    }
}
```

```php
// ProductResource
public static function pages(): array
{
    return [
        'index' => ProductList::class, // ← the list page with the widget bands
        'create' => CreateProduct::class,
        'edit' => EditProduct::class,
    ];
}
```

The widgets themselves are ordinary [`StatsWidget`](../widgets/stats-widget.md) /
[`ChartWidget`](../widgets/chart-widget.md) services — they compute their own data
through dependency injection, and the framework adds no aggregation API.

## Composing the layout

`ListWidgetsConfiguration` is the same shape as a dashboard's configuration:

| Method | Description |
| --- | --- |
| `widgets(array $widgetClasses): static` | A flat stack — each class wrapped in a full-width [`WidgetSlot`](../widgets/dashboards.md). |
| `schema(array $components): static` | A tree of `WidgetSlot`s nested in layout containers (`Grid`, `Section`, `Fieldset`, `Flex`). Columns come from `Grid::make(N)`, exactly as in a form. |
| `getComponents(): list<Component>` | The top-level nodes (for rendering). |

Each widget keeps its independent lifecycle — per-widget `canView()`, polling and
refresh — because each renders through the standalone `Atrium:Widget` host.

## Resource context

Every slot widget automatically receives the **resource-identity context** as
params: `resource` (the slug), `pathPrefix`, and the singular/plural labels. A
widget reads them through its param accessor to scope its own query or build a URL,
without the integrator wiring anything:

```php
public function getStats(): array
{
    $slug = $this->getParam('resource'); // e.g. 'product'
    // …
}
```

## The reactivity boundary

> **List widgets do not react to the table's live search or filters.** They
> reflect the **unfiltered** resource.

The table's interactive state (search, filters, sort, page) lives inside the
sibling `DataTable` Live Component; a widget is a separate component that mounts
independently and never sees it. So a header widget showing "12 products" does not
tick down to "3" when the user filters the table to three rows — it shows the whole
catalogue. If you need a figure scoped to a fixed subset, encode that in the
widget's own query (e.g. a status filter), not the table's live state.

## See also

- [Widgets overview](../widgets/overview.md) — building `StatsWidget` / `ChartWidget`
- [Dashboards](../widgets/dashboards.md) — the same `WidgetSlot` + layout composition
- [Pages](../pages/overview.md) — the list page that hosts these bands
- [Table configuration](table-configuration.md) — the table the bands sit around
