<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Post;

/**
 * A parent resource that forbids dissociating any child — proves the relation
 * manager hides and refuses the Detach action when the parent denies it (REL-12).
 */
final class DenyDissociatePostResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Post::class;
    }

    public function getSlug(): string
    {
        return 'post-deny';
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

    public function canDissociate(object $parent, object $child): bool
    {
        return false;
    }
}
