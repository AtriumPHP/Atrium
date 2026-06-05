<?php

declare(strict_types=1);

namespace Atrium\Tests\Twig\Components;

use Atrium\DataProvider\ArrayDataProvider;
use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Entity\Post;
use Atrium\Tests\Fixtures\Resource\CommentRelResource;
use Atrium\Twig\Components\RelationManagers;
use PHPUnit\Framework\TestCase;

final class RelationManagersTest extends TestCase
{
    private function host(AdminResource $resource): RelationManagers
    {
        $registry = new ResourceRegistry([$resource, new CommentRelResource()]);
        $dataProvider = new ArrayDataProvider([Post::class => [new Post(1, 'First')]]);

        $host = new RelationManagers($registry, $dataProvider);
        $host->resource = $resource->getSlug();
        $host->parentId = '1';

        return $host;
    }

    public function testHidesRelationsWhoseVisiblePredicateIsFalseForTheParent(): void
    {
        $resource = new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Post::class;
            }

            /** @return list<Relation> */
            public function relations(): array
            {
                return [
                    Relation::make('comments')
                        ->oneToMany(CommentRelResource::class)
                        ->foreignKey('postId')
                        ->visible(static fn (object $parent): bool => false),
                ];
            }
        };

        self::assertSame([], $this->host($resource)->getRelations());
        self::assertFalse($this->host($resource)->hasRelations());
    }

    public function testKeepsVisibleRelations(): void
    {
        $resource = new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Post::class;
            }

            /** @return list<Relation> */
            public function relations(): array
            {
                return [
                    Relation::make('comments')
                        ->oneToMany(CommentRelResource::class)
                        ->foreignKey('postId'),
                ];
            }
        };

        $relations = $this->host($resource)->getRelations();
        self::assertCount(1, $relations);
        self::assertSame('comments', $relations[0]['name']);
    }
}
