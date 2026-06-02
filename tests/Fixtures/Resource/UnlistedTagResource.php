<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture exercising shouldRegisterNavigation(): the resource is fully reachable
 * (its pages work) but never appears in the navigation menu.
 */
final class UnlistedTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'unlisted-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('name')]);
    }

    public function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
