<?php

declare(strict_types=1);

namespace Atrium\Tests\Resource;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\DataProvider\DataQuery;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;
use PHPUnit\Framework\TestCase;

final class ResourceHooksTest extends TestCase
{
    public function testDefaultsAreOpenAndNoOp(): void
    {
        $resource = $this->resource();
        $record = new Tag(1, 'A', 'a');

        self::assertTrue($resource->canViewAny());
        self::assertTrue($resource->canCreate());
        self::assertTrue($resource->canEdit($record));
        self::assertTrue($resource->canDelete($record));
        self::assertTrue($resource->canView($record));

        // Mutate hooks are identity; before/after hooks do not throw.
        self::assertSame(['name' => 'A'], $resource->mutateFormDataBeforeFill(['name' => 'A'], 'edit'));
        self::assertSame(['name' => 'A'], $resource->mutateFormDataBeforeValidate(['name' => 'A'], 'create'));
        self::assertSame(['name' => 'A'], $resource->mutateFormDataBeforeSave(['name' => 'A'], 'create'));
        $resource->afterValidate(['name' => 'A'], 'create');
        $resource->beforeSave($record, 'create');
        $resource->afterSave($record, 'create');
        $resource->beforeDelete($record);
        $resource->afterDelete($record);
    }

    public function testCanDispatchesByAbility(): void
    {
        $record = new Tag(1, 'A', 'a');

        $resource = new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Tag::class;
            }

            public function canViewAny(): bool
            {
                return false;
            }

            public function canCreate(): bool
            {
                return false;
            }

            public function canEdit(object $record): bool
            {
                return true;
            }

            public function canDelete(object $record): bool
            {
                return false;
            }
        };

        self::assertFalse($resource->can('viewAny'));
        self::assertFalse($resource->can('create'));
        self::assertTrue($resource->can('edit', $record));
        self::assertFalse($resource->can('delete', $record));
        // Record-scoped abilities deny without a record; unknown abilities allow.
        self::assertFalse($resource->can('edit'));
        self::assertTrue($resource->can('something-custom'));
    }

    public function testHandlePersistenceHooksDelegateToTheWriterByDefault(): void
    {
        $resource = $this->resource();
        $writer = new ArrayDataWriter();

        $created = new Tag(1, 'A', 'a');
        $resource->handleRecordCreation($created, $writer);
        self::assertSame([$created], $writer->records[Tag::class] ?? []);

        $updated = new Tag(2, 'B', 'b');
        $resource->handleRecordUpdate($updated, $writer);
        self::assertContains($updated, $writer->records[Tag::class] ?? []);
    }

    public function testScopeQueryDefaultsToNoScope(): void
    {
        self::assertSame([], $this->resource()->scopeFilters());
        self::assertSame([], $this->resource()->scopeQuery(new DataQuery())->filters);
    }

    private function resource(): AdminResource
    {
        return new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Tag::class;
            }
        };
    }
}
