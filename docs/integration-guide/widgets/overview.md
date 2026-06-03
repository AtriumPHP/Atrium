# Widgets & dashboards

> Self-contained, independently-refreshing UI units — stat cards and charts —
> configured in PHP, composed onto dashboards, and embeddable anywhere.

## When to use

Reach for a **widget** when you want a small, focused piece of read-only UI that
refreshes on its own: a "1,240 orders" stat card, a sales chart, a queue depth
gauge. Compose several onto a **dashboard** (a routable panel page), or drop a
single one into any Twig template.

You **don't** use a widget for editable data (that's a [form](../forms/overview.md))
or a record list (that's a [table](../tables/table-configuration.md)).

## How it fits together

A widget is a **descriptor service**, not a Live Component — exactly like the
[table](../tables/table-configuration.md) and [form](../forms/overview.md) split.
You write a subclass and return already-computed values; a single generic Live
Component host (`Atrium:Widget`) mounts it, enforces authorization, renders it,
and refreshes it independently. Two concrete families ship:

- **[Stats widget](stats-widget.md)** — a row of stat cards.
- **[Chart widget](chart-widget.md)** — a Chart.js chart (via Symfony UX Chart.js,
  no JS build step).

A **[dashboard](dashboards.md)** lists widget classes and lays them out in a
responsive grid; you can register several, each routable and in the navigation.

## You compute the data

Widgets are services, so a widget **injects whatever produces its numbers** — a
repository, the [`DataProviderInterface`](../data/providers.md), a DBAL
connection — and returns the result. The framework adds **no** aggregation API
and stays storage-agnostic:

```php
final class RevenueStat extends StatsWidget
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function getStats(): array
    {
        return [Stat::make('Revenue', $this->orders->revenueThisMonth())];
    }
}
```

## Shared widget API

Every widget (stats or chart) inherits these from `Atrium\Widget\Widget`:

| Method | Description |
| --- | --- |
| `canView(): bool` | Authorization gate, enforced on mount **and** refresh. Default `true`. |
| `getColumnSpan(): int\|string\|array` | Grid width: `int` (N columns at `lg`+), `'full'`, or a responsive map (`['md' => 2, 'xl' => 3]`). |
| `getPollingInterval(): ?string` | Auto-refresh cadence (`'10s'`, `'500ms'`, `'2m'`); `null` (default) disables polling. |

Widgets refresh **independently**: a refresh (manual or polled) re-renders only
that widget and recomputes its data, never reloading the page or its neighbours.

## Security

The host identifies a widget by **class name**, passed as a non-writable
(HMAC-checksummed) Live Component prop — a client cannot repoint it at another
class. Resolution goes **only** through the registry of tagged widgets, so an
unknown or forged class fails closed (renders nothing). `canView()` is enforced
server-side on every render.

## See also

- [Stats widget](stats-widget.md) — `StatsWidget` and `Stat`
- [Chart widget](chart-widget.md) — `ChartWidget` and UX Chart.js
- [Dashboards](dashboards.md) — compose widgets into routable pages
- [Embedding widgets](embedding.md) — drop a widget into any Twig template
- [Data providers](../data/providers.md) — one way to feed a widget its numbers
