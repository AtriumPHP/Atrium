<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture exercising the navigation badge + sort hooks: it shows a red "7" badge
 * and sorts ahead of the unweighted entries.
 */
final class BadgedTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'badged-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('name')]);
    }

    public function getNavigationBadge(): string
    {
        return '7';
    }

    public function getNavigationBadgeColor(): string
    {
        return 'red';
    }

    public function getNavigationSort(): int
    {
        return -5;
    }
}
