<?php

declare(strict_types=1);

namespace Atrium\Tests\Action;

use Atrium\Action\ActionContext;
use Atrium\Action\NestedActionContext;
use PHPUnit\Framework\TestCase;

final class NestedActionContextTest extends TestCase
{
    public function testPrependsTheParentSegmentToEveryRecordUrl(): void
    {
        $context = new NestedActionContext('/admin', 'project', '1', 'task', '7');

        self::assertSame('/admin/project/1/task', $context->resourceUrl());
        self::assertSame('/admin/project/1/task/7', $context->recordRootUrl());
        self::assertSame('/admin/project/1/task/7/edit', $context->recordUrl('edit'));
    }

    public function testIsAnActionContext(): void
    {
        self::assertInstanceOf(ActionContext::class, new NestedActionContext('/admin', 'project', '1', 'task', '7'));
    }

    public function testParentIdIsUrlEncoded(): void
    {
        $context = new NestedActionContext('/admin', 'project', 'a b', 'task', '7');
        self::assertSame('/admin/project/a%20b/task', $context->resourceUrl());
    }
}
