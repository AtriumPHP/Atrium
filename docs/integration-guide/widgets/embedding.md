# Embedding widgets

> Drop any widget into any Twig template with a single component tag — not just
> onto a dashboard.

## When to use

A [dashboard](dashboards.md) is the usual home for widgets, but a widget is a
standalone Live Component, so you can place one anywhere: above a record's detail
view, in a custom report controller's template, in an email preview. Use this
when you want a reactive stat or chart outside the dashboard grid.

## Example

```twig
{# Any Twig template. The widget class is passed as the `widget` attribute —
   `class` is reserved by Twig components for CSS classes. #}
<twig:Atrium:Widget widget="App\\Widget\\RevenueStat" />
```

### Passing context

A widget can read scalar **params** supplied at the embed site — an id, a date
range, a filter value. Pass them with `:params` (a bound expression):

```twig
<twig:Atrium:Widget
    widget="App\\Widget\\OrdersPerDay"
    :params="{ range: 90, authorId: author.id }"
/>
```

Read them inside the widget through `$this->params`:

```php
final class OrdersPerDay extends ChartWidget
{
    public function getData(): array
    {
        $range = $this->params['range'] ?? 30;
        // …compute using $range, then return Chart.js data
    }
}
```

Params are **scalars only** (and arrays of scalars) — never a hydrated entity.
Pass an id and re-fetch through your injected services; this keeps the embed
server-driven and the payload checksummable.

## Notes

- The widget class must be a **registered** widget (any `Atrium\Widget\Widget`
  subclass is autoconfigured). An unknown class renders nothing.
- `canView()` still applies — an embedded widget the viewer may not see renders
  nothing.
- Width is set by a [`WidgetSlot`](dashboards.md#widgetslot-api) in a dashboard
  layout; a standalone embed has no span — wrap it yourself to size it.
- Refresh and polling work the same as on a dashboard.

## See also

- [Widgets overview](overview.md) — the widget contract and security model
- [Dashboards](dashboards.md) — the grid-based way to compose widgets
