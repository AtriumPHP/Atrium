<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Post;
use Atrium\Tests\Fixtures\Relation\CommentManagerConfig;

/**
 * A Post-like parent whose `comments` relation extracts its table()/form() to a
 * dedicated {@see CommentManagerConfig} via ->using() (REL-19). Slug is overridden
 * to `using-post` so it doesn't collide with {@see PostRelResource} (both wrap Post).
 */
final class UsingPostResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Post::class;
    }

    public function getSlug(): string
    {
        return 'using-post';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('title')]);
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('comments')->oneToMany(CommentRelResource::class)
                ->foreignKey('postId')->using(CommentManagerConfig::class),
        ];
    }
}
