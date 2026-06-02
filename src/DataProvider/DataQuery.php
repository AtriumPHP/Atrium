<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

/**
 * An immutable description of what a table wants from a data provider: an
 * optional search term (matched against a trusted set of fields), an optional
 * sort, and a pagination window.
 *
 * This object carries only developer-trusted field names and user-supplied
 * values kept separate, so adapters can bind the latter as query parameters
 * (see {@see DataProviderInterface}).
 */
final readonly class DataQuery
{
    public const string SORT_ASC = 'asc';
    public const string SORT_DESC = 'desc';

    /**
     * @param list<string>                    $searchableFields field names search is matched against
     * @param array<string, scalar|bool|null> $filters          trusted field => equality value conditions
     */
    public function __construct(
        public ?string $search = null,
        public array $searchableFields = [],
        public ?string $sortField = null,
        public string $sortDirection = self::SORT_ASC,
        public int $offset = 0,
        public int $limit = 25,
        public array $filters = [],
    ) {
    }

    /**
     * A copy of this query with extra equality conditions merged in, last value
     * winning per field. Used by {@see \Atrium\Resource\AdminResource::scopeQuery()}
     * to add scope (tenant, ownership, soft-delete) without reconstructing the
     * whole immutable query.
     *
     * @param array<string, scalar|bool|null> $filters
     */
    public function withFilters(array $filters): self
    {
        return new self(
            search: $this->search,
            searchableFields: $this->searchableFields,
            sortField: $this->sortField,
            sortDirection: $this->sortDirection,
            offset: $this->offset,
            limit: $this->limit,
            filters: [...$this->filters, ...$filters],
        );
    }

    /**
     * Whether a non-empty search term should be applied to at least one field.
     */
    public function hasSearch(): bool
    {
        return null !== $this->search && '' !== trim($this->search) && [] !== $this->searchableFields;
    }

    /**
     * The trimmed search term, or null when there is nothing to search for.
     */
    public function searchTerm(): ?string
    {
        return $this->hasSearch() ? trim((string) $this->search) : null;
    }

    /**
     * Normalised sort direction, always either {@see SORT_ASC} or {@see SORT_DESC}.
     */
    public function normalizedSortDirection(): string
    {
        return self::SORT_DESC === strtolower($this->sortDirection) ? self::SORT_DESC : self::SORT_ASC;
    }
}
