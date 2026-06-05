<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Post;

final class PostRelResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Post::class;
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
        ];
    }
}
