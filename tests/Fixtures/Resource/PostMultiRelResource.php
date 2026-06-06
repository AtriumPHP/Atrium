<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Post;

/** A parent with two relations — proves the host's tab strip (REL-08). */
final class PostMultiRelResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Post::class;
    }

    public function getSlug(): string
    {
        return 'post-multi';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('title')]);
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('comments')
                ->oneToMany(CommentRelResource::class)
                ->foreignKey('postId')
                ->recordTitle('body'),
            Relation::make('tags')
                ->manyToMany(TagRelResource::class)
                ->pivotTable('post_tag')
                ->pivotKeys('post_id', 'tag_id')
                ->pivotColumns(['note'])
                ->recordTitle('name'),
        ];
    }
}
