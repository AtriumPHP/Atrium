<?php

declare(strict_types=1);

namespace Atrium\Tests\Page;

use Atrium\Page\PageContext;
use PHPUnit\Framework\TestCase;

final class PageContextNestedTest extends TestCase
{
    public function testFlatContextHasNoParentRecords(): void
    {
        $context = new PageContext('task', '/admin', '7', 'Task', 'Tasks');
        self::assertSame([], $context->parentRecords);
    }

    public function testNestedUrlBuildsTheFiveSegmentForm(): void
    {
        $context = new PageContext(
            'task', '/admin', '7', 'Task', 'Tasks',
            parentResourceSlug: 'project', parentRecordId: '1', parentRecords: [(object) ['id' => 1]],
        );

        self::assertSame('/admin/project/1/task', $context->nestedUrl('index'));
        self::assertSame('/admin/project/1/task/new', $context->nestedUrl('create'));
        self::assertSame('/admin/project/1/task/7/edit', $context->nestedUrl('edit', '7'));
        self::assertSame('/admin/project/1/task/7', $context->nestedUrl('view', '7'));
    }

    public function testNestedUrlFallsBackToFlatWhenNotNested(): void
    {
        $context = new PageContext('task', '/admin', '7', 'Task', 'Tasks');

        self::assertSame('/admin/task', $context->nestedUrl('index'));
        self::assertSame('/admin/task/new', $context->nestedUrl('create'));
        self::assertSame('/admin/task/7/edit', $context->nestedUrl('edit', '7'));
        self::assertSame('/admin/task/7', $context->nestedUrl('view', '7'));
    }
}
