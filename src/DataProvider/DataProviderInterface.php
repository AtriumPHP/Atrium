<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

/**
 * Backend-agnostic read abstraction for tables and infolists.
 *
 * All reads in the framework go through this interface so the storage backend
 * (Doctrine ORM by default, but API Platform / array / ODM are possible) can be
 * swapped with a single alias change. Implementations MUST bind user-supplied
 * values (the search term) as parameters, never interpolate them; field names
 * in the {@see DataQuery} come from trusted developer configuration.
 *
 * A companion writer interface (DAT-03) for create/update/delete is added in the
 * forms phase.
 */
interface DataProviderInterface
{
    /**
     * @param class-string $entityClass
     *
     * @return iterable<object>
     */
    public function fetch(string $entityClass, DataQuery $query): iterable;

    /**
     * Total number of records matching the query's filter (ignoring pagination).
     *
     * @param class-string $entityClass
     */
    public function count(string $entityClass, DataQuery $query): int;

    /**
     * Load a single record by its identifier, or null if not found.
     *
     * `$idField` names the property the `$id` is matched against — `id` by
     * default, or a resource's
     * {@see \Atrium\Resource\AdminResource::getIdentifierField()} (a custom
     * primary key, or a natural key such as a slug). Its values must be unique;
     * implementations resolve at most one record by it.
     *
     * When `$filters` is non-empty the record must also satisfy every equality
     * condition (the same field => value contract as {@see DataQuery::$filters}),
     * so a resource's {@see \Atrium\Resource\AdminResource::scopeQuery()} scope
     * applies to record resolution too: an id outside the scope resolves to null,
     * not just an unactionable row. Implementations MUST bind the values as
     * parameters; the field names come from trusted developer configuration.
     *
     * @param class-string                    $entityClass
     * @param array<string, scalar|bool|null> $filters     trusted field => equality value scope conditions
     * @param string                          $idField     trusted name of the identifying property
     */
    public function find(string $entityClass, int|string $id, array $filters = [], string $idField = 'id'): ?object;
}
