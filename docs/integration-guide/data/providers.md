# Data providers & writer

> The storage abstraction every read and write goes through — Doctrine by default,
> swappable for anything, with no Doctrine types leaking into the rest of Atrium.

## When to use

You usually **don't** touch this layer — with Doctrine installed it's wired
automatically and resources just work. Read this when you want to understand how
data flows, swap the backend (API Platform, an array, a custom store), or write a
custom provider. Two contracts split reads from writes:

- **`DataProviderInterface`** — reads (list, count, find).
- **`DataWriterInterface`** — writes (create, update, delete) and the transaction
  boundary.

Everything in the framework (tables, forms, actions) goes through these, so the
storage backend is a single alias away from being replaced.

## The default: Doctrine

When DoctrineBundle is registered, Atrium wires `DoctrineDataProvider` and
`DoctrineDataWriter` automatically and aliases the two interfaces to them. Your
entities are queried and persisted through Doctrine with no configuration. The
Doctrine adapter is the **only** place in Atrium that references Doctrine types —
the core stays storage-agnostic.

## `DataProviderInterface`

```php
interface DataProviderInterface
{
    /** @return iterable<object> */
    public function fetch(string $entityClass, DataQuery $query): iterable;
    public function count(string $entityClass, DataQuery $query): int;
    public function find(string $entityClass, int|string $id, array $filters = [], string $idField = 'id'): ?object;
}
```

| Method | Description |
| --- | --- |
| `fetch($class, $query)` | The rows for a query (search, sort, pagination, filters). |
| `count($class, $query)` | Total rows matching the query, ignoring pagination. |
| `find($class, $id, $filters = [], $idField = 'id')` | One record by `$id`, matched against the `$idField` property (a resource's [`getIdentifierField()`](../resources/overview.md#record-identity), `id` by default); `$filters` scopes it (see [query scoping](query-scoping.md)) so an out-of-scope id resolves to `null`. |

Implementations **must** bind user-supplied values (the search term, filter values)
as parameters — field names come from trusted developer configuration, values
never do.

## `DataWriterInterface`

```php
interface DataWriterInterface
{
    public function create(object $entity): void;
    public function update(object $entity): void;
    public function delete(object $entity): void;
    /** @return mixed the closure's return value */
    public function transactional(callable $work): mixed;
}
```

`transactional()` runs the work atomically — committing on success, rolling back
and re-throwing on failure. Atrium wraps each save and delete in it so the
[lifecycle hooks](../resources/lifecycle-hooks.md) around persistence are atomic.
The Doctrine writer uses `wrapInTransaction`; a backend without transactions just
runs the work.

## `DataQuery`

An immutable description of what a table wants. You mostly meet it in
[query scoping](query-scoping.md); its shape:

| Property | Meaning |
| --- | --- |
| `search` / `searchableFields` | The term and the fields it matches (trusted names). |
| `sortField` / `sortDirection` | Sort column and direction. |
| `offset` / `limit` | Pagination window. |
| `filters` | `field => value` equality conditions (`null` = IS NULL). |

`withFilters(array): self` returns a copy with extra conditions merged in — the
ergonomic way to add scope.

## Swapping the backend

Selecting a different store is a single alias override. Implement the two
interfaces for your backend and alias them:

```php
# config/services.yaml
services:
    App\Admin\ApiDataProvider: ~
    Atrium\DataProvider\DataProviderInterface: '@App\Admin\ApiDataProvider'

    App\Admin\ApiDataWriter: ~
    Atrium\DataProvider\DataWriterInterface: '@App\Admin\ApiDataWriter'
```

Nothing else changes — resources, tables and forms are unaware of the backend.

## The array adapter

`ArrayDataProvider` and `ArrayDataWriter` keep records in memory. They power the
test suite and fixtures, and prove the data layer isn't tied to Doctrine — a good
reference when writing your own adapter. They traverse property paths (including
relation columns like `author.name`) through Symfony's PropertyAccessor.

## See also

- [Query scoping](query-scoping.md) — restrict which records a resource exposes
- [Lifecycle hooks](../resources/lifecycle-hooks.md) — custom persistence, atomic saves
- [Columns](../tables/columns.md) — how `DataQuery` field names map to columns
