<?php

declare(strict_types=1);

namespace Atrium\Tests\Page;

use Atrium\Page\CreatePage;
use Atrium\Page\EditPage;
use Atrium\Page\ListPage;
use Atrium\Page\Page;
use Atrium\Page\PageContext;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testHeadingDefaultsPerScreen(): void
    {
        $context = new PageContext('customer', '/admin', null, 'Customer', 'Customers');

        self::assertSame('Customers', (new ListPage())->getHeading($context));
        self::assertSame('New Customer', (new CreatePage())->getHeading($context));
        self::assertSame('Edit Customer', (new EditPage())->getHeading($context));
    }

    public function testTitleDefaultsToHeadingAndSubheadingToNull(): void
    {
        $context = new PageContext('customer', '/admin', null, 'Customer', 'Customers');
        $page = new CreatePage();

        self::assertSame('New Customer', $page->getTitle($context));
        self::assertNull($page->getSubheading($context));
    }

    public function testCustomHeadingAndSubheading(): void
    {
        $page = new class extends EditPage {
            public function getHeading(PageContext $context): string
            {
                return 'Editing '.$context->singularLabel;
            }

            public function getSubheading(PageContext $context): string
            {
                return 'Record #'.$context->entityId;
            }
        };
        $context = new PageContext('customer', '/admin', '42', 'Customer', 'Customers');

        self::assertSame('Editing Customer', $page->getHeading($context));
        self::assertSame('Record #42', $page->getSubheading($context));
    }

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
