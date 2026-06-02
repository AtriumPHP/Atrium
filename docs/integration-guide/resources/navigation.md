# Navigation & access

> Control whether a resource is reachable, whether it appears in the menu, where
> it sits, and what badge it shows.

## When to use

Every resource is auto-discovered and listed in the sidebar by default. Override
these hooks to change that:

- **Hide a resource entirely** (and 403 its pages) — `canAccess()`.
- **Keep it reachable but out of the menu** (a detail resource you only link to)
  — `shouldRegisterNavigation()`.
- **Order the menu** — `getNavigationSort()`.
- **Group, icon, badge** — `getNavigationGroup()`, `getNavigationIcon()`,
  `getNavigationBadge()`.

`canAccess()` is the resource-level gate: it hides the nav entry **and** makes
every page return 403. It defaults to [`canViewAny()`](authorization.md), so by
default "can see the list" and "can reach the resource" coincide. The
page-specific abilities (`canCreate`, `canEdit`) still apply on top.

## Example

```php
use Atrium\Resource\AdminResource;
use App\Entity\Order;

final class OrderResource extends AdminResource
{
    public function __construct(
        private readonly Security $security,
        private readonly OrderRepository $orders,
    ) {
    }

    public function getEntityClass(): string
    {
        return Order::class;
    }

    public function getNavigationGroup(): ?string
    {
        return 'Shop';
    }

    public function getNavigationIcon(): ?string
    {
        return 'cart';
    }

    public function getNavigationSort(): ?int
    {
        return 10; // lower numbers come first; unsorted entries follow
    }

    // A live count of orders needing attention.
    public function getNavigationBadge(): ?string
    {
        $pending = $this->orders->countPending();

        return $pending > 0 ? (string) $pending : null;
    }

    public function getNavigationBadgeColor(): string
    {
        return 'red';
    }

    // Only staff can reach orders at all.
    public function canAccess(): bool
    {
        return $this->security->isGranted('ROLE_STAFF');
    }
}
```

## API reference

| Method | Description |
| --- | --- |
| `canAccess(): bool` | Whether the resource is reachable. Hides the nav entry and 403s every page. Defaults to `canViewAny()`. |
| `shouldRegisterNavigation(): bool` | Whether to list the resource in the menu. Return false to keep pages reachable but hide the entry. Defaults to true. |
| `getNavigationSort(): ?int` | Sort weight (lower first). Unsorted entries (`null`) follow in registration order. |
| `getNavigationBadge(): ?string` | Badge text next to the entry, or `null` for none. |
| `getNavigationBadgeColor(): string` | Badge colour key (`gray`, `primary`, `red`, `green`, `amber`, `sky`). Defaults to `primary`. |
| `getNavigationGroup(): ?string` | Group heading the entry is listed under, or `null`. |
| `getNavigationIcon(): ?string` | Icon identifier for the entry, or `null`. |
| `getLabel(): string` | The menu label (pluralised). |

> **Render slots** (custom HTML in the page header/footer, `beforeRender`) are a
> separate theming concern and are not part of this navigation surface yet.

## See also

- [Authorization](authorization.md) — per-page and per-record gating
- [Query scoping](../data/query-scoping.md) — which records a resource exposes
