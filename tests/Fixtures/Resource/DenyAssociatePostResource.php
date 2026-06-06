<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Post;

/**
 * A parent resource that forbids associating any child — proves the relation
 * manager refuses the Attach execution path when the parent denies it (REL-12).
 * (The Attach button itself can't gate on canAssociate, which needs a child; the
 * guarantee is enforced at submit, which this fixture exercises.).
 */
final class DenyAssociatePostResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Post::class;
    }

    public function getSlug(): string
    {
        return 'post-deny-assoc';
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

    public function canAssociate(object $parent, object $child): bool
    {
        return false;
    }
}
