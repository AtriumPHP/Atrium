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
     * @param class-string $entityClass
     */
    public function find(string $entityClass, int|string $id): ?object;
}
