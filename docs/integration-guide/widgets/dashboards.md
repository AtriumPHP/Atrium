# Dashboards

> Routable panel pages that compose [widgets](overview.md) into a responsive grid.
> Register several; one is the panel root.

## When to use

Use a `Dashboard` to give a set of widgets a home: a finance dashboard, an ops
overview, a per-team landing page. Each dashboard is reachable at its own URL and
listed in the sidebar. Like a [page](../pages/overview.md), a dashboard is a
descriptor class, not a Live Component — the widgets it lays out are the reactive
parts.

## Example

```php
namespace App\Dashboard;

use App\Widget\RevenueStat;
use App\Widget\SalesChart;
use Atrium\Dashboard\Dashboard;

final class FinanceDashboard extends Dashboard
{
    public function getTitle(): string
    {
        return 'Finance';
    }

    public function getWidgets(): array
    {
        return [RevenueStat::class, SalesChart::class];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }

    public function getNavigationIcon(): ?string
    {
        return 'banknotes';
    }

    public function getNavigationSort(): ?int
    {
        return 10;
    }

    public function canAccess(): bool
    {
        return true; // gate via your authorization checker / voter
    }
}
```

Atrium discovers it automatically (no tags, no YAML). It is reachable at
`/admin/finance`, listed in the sidebar (sorted among the resources), and 403s for
users `canAccess()` denies.

## Routing & the panel root

Dashboards and resources share the `/admin/{slug}` namespace, resolved in that
order (a dashboard wins). Slugs must therefore be **unique across dashboards and
resources** — a collision fails fast with a clear error.

`/admin` (the panel root) renders the dashboard registered at the **root slug**
(`'dashboard'`), or the built-in welcome screen when none claims it. To replace
the welcome, register a dashboard whose `getSlug()` returns `'dashboard'`:

```php
final class HomeDashboard extends Dashboard
{
    public function getSlug(): string
    {
        return \Atrium\Dashboard\DefaultDashboard::ROOT_SLUG; // 'dashboard'
    }
}
```

## `Dashboard` API

| Method | Description |
| --- | --- |
| `getWidgets(): array` | Widget descriptor classes to render, in order. Default `[]`. |
| `getColumns(): int\|array` | Grid columns: `int` (N at `lg`+) or a responsive map. Default `2`. |
| `getTitle(): string` | Page heading and default nav label. Defaults from the slug. |
| `getSlug(): string` | URL slug. Defaults to the kebab-cased class name without a trailing `Dashboard`. |
| `canAccess(): bool` | Authorization gate — 403s the page and hides the nav entry. Default `true`. |
| `getNavigationLabel(): string` | Sidebar label. Defaults to the title. |
| `getNavigationIcon(): ?string` | Sidebar icon. Default `dashboard`. |
| `getNavigationSort(): ?int` | Menu order (lower first), merged with resources. Default `null` (after weighted entries). |
| `getNavigationGroup(): ?string` | Optional navigation group. |
| `getNavigationBadge(): ?string` / `getNavigationBadgeColor(): string` | Optional badge next to the entry. |
| `shouldRegisterNavigation(): bool` | Return `false` to keep the page reachable but hidden from the menu. Default `true`. |

## See also

- [Widgets overview](overview.md) — the widgets a dashboard composes
- [Stats widget](stats-widget.md) · [Chart widget](chart-widget.md)
- [Embedding widgets](embedding.md) — use a widget outside a dashboard
- [Navigation](../resources/navigation.md) — the shared nav vocabulary
