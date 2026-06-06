<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Relation\ParentRelation;
use Atrium\Relation\ParentRelationResolver;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Entity\Task;
use Atrium\Tests\Fixtures\Resource\ProjectResource;
use Atrium\Tests\Fixtures\Resource\TaskResource;
use PHPUnit\Framework\TestCase;

final class ParentRelationResolverTest extends TestCase
{
    private function resolver(AdminResource ...$resources): ParentRelationResolver
    {
        return new ParentRelationResolver(new ResourceRegistry($resources));
    }

    public function testResolvesAValidNestedDeclaration(): void
    {
        $resolved = $this->resolver(new ProjectResource(), new TaskResource())
            ->resolve(new TaskResource());

        self::assertInstanceOf(ProjectResource::class, $resolved->parentResource);
        self::assertSame('tasks', $resolved->relation->getName());
        self::assertSame('projectId', $resolved->foreignKey);
        self::assertSame('name', $resolved->recordTitleAttribute); // Project's display attribute
    }

    public function testThrowsWhenResourceIsNotNested(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not nested/');
        $this->resolver(new ProjectResource())->resolve(new ProjectResource());
    }

    public function testThrowsWhenParentResourceIsNotRegistered(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/not registered/');
        // TaskResource present, ProjectResource missing from the registry.
        $this->resolver(new TaskResource())->resolve(new TaskResource());
    }

    public function testThrowsWhenNamedRelationshipIsMissing(): void
    {
        // TaskResource is final; extend the base directly to forge a broken parent().
        $child = new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Task::class;
            }

            public function parent(): ParentRelation
            {
                return ParentRelation::make(ProjectResource::class)
                    ->relationship('nope')->foreignKey('projectId');
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/no relation "nope"/');
        $this->resolver(new ProjectResource(), $child)->resolve($child);
    }

    public function testThrowsWhenForeignKeyDisagreesWithTheRelation(): void
    {
        $child = new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Task::class;
            }

            public function parent(): ParentRelation
            {
                return ParentRelation::make(ProjectResource::class)
                    ->relationship('tasks')->foreignKey('wrong_id');
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/foreignKey/');
        $this->resolver(new ProjectResource(), $child)->resolve($child);
    }
}
