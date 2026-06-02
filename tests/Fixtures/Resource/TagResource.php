<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Inline (small) resource fixture — mirrors the §9a DX example.
 */
final class TagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function columns(): array
    {
        return [
            Column::make('name')->sortable()->searchable(),
            Column::make('slug')->searchable(),
        ];
    }
}
