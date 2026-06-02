<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture exercising table-level options: a configured default sort and a small
 * page size with a per-page selector. Backed by the 12 sample Tag records.
 */
final class PaginatedTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'paginated-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table
            ->columns([Column::make('name')->sortable()->searchable()])
            ->defaultSort('name', 'desc')
            ->paginated(5, [5, 10, 25]);
    }
}
