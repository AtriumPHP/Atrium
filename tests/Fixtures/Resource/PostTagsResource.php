<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Post;

/** Parent with a many-to-many `tags` relation (Post ↔ Tag via post_tag). */
class PostTagsResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Post::class;
    }

    public function getSlug(): string
    {
        return 'post-tags';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('title')]);
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('tags')
                ->manyToMany(TagRelResource::class)
                ->pivotTable('post_tag')
                ->pivotKeys('post_id', 'tag_id')
                ->pivotColumns(['note'])
                ->recordTitle('name'),
        ];
    }
}
