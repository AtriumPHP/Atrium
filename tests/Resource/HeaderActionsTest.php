<?php

declare(strict_types=1);

namespace Atrium\Tests\Resource;

use Atrium\Action\Action;
use Atrium\Page\CreatePage;
use Atrium\Page\EditPage;
use Atrium\Page\ListPage;
use Atrium\Page\PageContext;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Resource\TagResource;
use PHPUnit\Framework\TestCase;

final class HeaderActionsTest extends TestCase
{
    private PageContext $context;

    protected function setUp(): void
    {
        $this->context = new PageContext('tag', '/admin', null, 'Tag', 'Tags');
    }

    public function testListPageProvidesTheDefaultNewActionAndOtherScreensNone(): void
    {
        // The "New" button is the ListPage's default; create/edit have none.
        $resource = new TagResource();

        self::assertCount(1, $resource->resolveHeaderActions('index', $this->context));
        self::assertSame([], $resource->resolveHeaderActions('create', $this->context));
        self::assertSame([], $resource->resolveHeaderActions('edit', $this->context));
    }

    public function testDedicatedPageHeaderActionsAreUsed(): void
    {
        $resource = new class extends AdminResource {
            public function getEntityClass(): string
            {
                return Tag::class;
            }

            public static function pages(): array
            {
                return [
                    'index' => CustomHeaderListPage::class,
                    'create' => CreatePage::class,
                    'edit' => EditPage::class,
                ];
            }
        };

        $actions = $resource->resolveHeaderActions('index', $this->context);

        self::assertCount(1, $actions);
        self::assertSame('custom', $actions[0]->getName());
    }
}

final class CustomHeaderListPage extends ListPage
{
    public function getHeaderActions(PageContext $context): array
    {
        return [Action::make('custom')];
    }
}
