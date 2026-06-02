# Query scoping

> Constrain *which* records a resource exposes — multi-tenancy, ownership,
> soft-deletes — at the data layer, so out-of-scope rows are invisible everywhere.

## When to use

Reach for `scopeQuery()` when a resource should only ever surface a **subset** of
its entity's rows:

- **Multi-tenancy** — only the current tenant's records.
- **Ownership** — only records belonging to the signed-in user.
- **Soft-deletes** — hide rows with a non-null `deletedAt`.

Scoping is different from [authorization](../resources/authorization.md). An
authorization hook (`canEdit`, `canDelete`) hides *actions* on a row the user can
still **see**; scoping removes the row from the result set entirely. They
complement each other — scope to filter the list, authorize to gate what can be
done with what remains. A correct multi-tenant resource usually does both.

The scope is applied in **four** places, so it is a real boundary rather than a
list cosmetic:

1. the list query,
2. the total count (and pagination),
3. select-all bulk actions ("all matching the query"),
4. single-record resolution — the edit page, the form, and every row/bulk action.

Because (4) is scoped, an id outside the scope resolves to `null`: the edit page
404s and a forged action request finds nothing to act on — even though the row
physically exists.

## Example

```php
use Atrium\DataProvider\DataQuery;
use Atrium\Resource\AdminResource;
use App\Entity\Article;

final class ArticleResource extends AdminResource
{
    public function __construct(private readonly TenantContext $tenant)
    {
    }

    public function getEntityClass(): string
    {
        return Article::class;
    }

    public function scopeQuery(DataQuery $query): DataQuery
    {
        return $query->withFilters([
            'tenantId'  => $this->tenant->id(), // only this tenant's rows
            'deletedAt' => null,                // hide soft-deleted rows
        ]);
    }
}
```

Resources are services, so inject whatever the scope needs (the tenant context,
`Security`, the request stack) through the constructor.

## How scope is expressed

Scope is a map of **`field => value` equality conditions**, the same contract as
`DataQuery::$filters`. A `null` value means `IS NULL`. Values are bound as query
parameters by the data provider; field names come from your (trusted)
configuration.

This covers the common cases — tenant id, owner id, a soft-delete flag or
timestamp. It does **not** express ranges, `IN (...)`, or `OR` predicates; for
those, scope at the data-provider/repository level (a Doctrine filter or a custom
provider) instead.

## API reference

| Method | Description |
| --- | --- |
| `scopeQuery(DataQuery $query): DataQuery` | Return a query narrowed to the records this resource exposes. Override it; defaults to the query unchanged (no scoping). |
| `DataQuery::withFilters(array $filters): self` | A copy of the query with extra `field => value` equality conditions merged in (last value wins per field). The ergonomic way to add scope. |
| `scopeFilters(): array` | *(final)* The scope's conditions alone, derived from `scopeQuery()`. Atrium uses it to scope single-record resolution; you rarely call it directly. |

`scopeQuery()` runs on every read, so keep it cheap and side-effect-free.

## See also

- [Authorization](../resources/authorization.md) — gate *actions* on visible rows
- [Record lifecycle hooks](../resources/lifecycle-hooks.md) — shape data around writes
