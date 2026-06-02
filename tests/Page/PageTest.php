<?php

declare(strict_types=1);

namespace Atrium\Tests\Page;

use Atrium\Page\EditPage;
use Atrium\Page\Page;
use Atrium\Page\PageContext;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testContextBuildsUrls(): void
    {
        $context = new PageContext('customer', '/admin', '42');

        self::assertSame('/admin/customer', $context->indexUrl());
        self::assertSame('/admin/customer/new', $context->createUrl());
        self::assertSame('/admin/customer/42/edit', $context->editUrl('42'));
    }

    public function testDefaultRedirectIsTheIndex(): void
    {
        $page = new EditPage();
        $context = new PageContext('customer', '/admin', '42');

        self::assertSame('/admin/customer', $page->getRedirectUrl($context));
    }

    public function testCustomRedirectHookTakesEffect(): void
    {
        $page = new class extends Page {
            public function getRedirectUrl(PageContext $context): ?string
            {
                // Stay on the edit screen after saving (null for create).
                return null === $context->entityId ? null : $context->editUrl($context->entityId);
            }
        };

        $context = new PageContext('customer', '/admin', '42');

        self::assertSame('/admin/customer/42/edit', $page->getRedirectUrl($context));
    }
}
