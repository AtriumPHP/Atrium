<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Exercises a **custom** row-click target (`recordUrl(Closure)`): a safe relative
 * URL for most rows, and an unsafe `javascript:` scheme for one — which the table
 * must drop rather than render as an href.
 */
final class RowUrlTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'row-url-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table
            ->columns([Column::make('name')])
            ->recordUrl(static fn (Tag $tag): string => 3 === $tag->id
                ? 'javascript:alert(1)'
                : '/go/'.$tag->id);
    }
}
