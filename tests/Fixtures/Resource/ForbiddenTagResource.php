<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture exercising canAccess(): the resource is unreachable — hidden from the
 * navigation and its pages return 403.
 */
final class ForbiddenTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'forbidden-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('name')]);
    }

    public function canAccess(): bool
    {
        return false;
    }
}
