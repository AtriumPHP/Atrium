<?php

declare(strict_types=1);

namespace Atrium\Tests\Resource;

use Atrium\Page\PageContext;
use Atrium\Tests\Fixtures\Resource\ListWidgetTagResource;
use Atrium\Tests\Fixtures\Resource\TagResource;
use Atrium\Widget\WidgetSlot;
use PHPUnit\Framework\TestCase;

final class ListWidgetsTest extends TestCase
{
    private PageContext $context;

    protected function setUp(): void
    {
        $this->context = new PageContext('list-widget-tag', '/admin', null, 'Tag', 'Tags');
    }

    public function testNoWidgetsWhenTheListPageDeclaresNone(): void
    {
        // The default ListPage returns empty header/footer configs.
        $resource = new TagResource();
        $context = new PageContext('tag', '/admin', null, 'Tag', 'Tags');

        self::assertSame([], $resource->resolveHeaderWidgets($context)->getComponents());
        self::assertSame([], $resource->resolveFooterWidgets($context)->getComponents());
    }

    public function testResolvesTheListPageWidgetsAndAppliesContext(): void
    {
        $resource = new ListWidgetTagResource();

        $header = $resource->resolveHeaderWidgets($this->context)->getComponents();
        self::assertCount(1, $header); // the Grid wrapping the stats slot

        $footer = $resource->resolveFooterWidgets($this->context)->getComponents();
        self::assertCount(1, $footer);
        self::assertInstanceOf(WidgetSlot::class, $footer[0]);
        // The resource-identity context rode down onto the slot.
        self::assertSame('list-widget-tag', $footer[0]->getParams()['resource']);
        self::assertSame('/admin', $footer[0]->getParams()['pathPrefix']);
    }
}
