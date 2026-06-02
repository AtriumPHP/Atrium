<?php

declare(strict_types=1);

namespace Atrium\Tests\Layout;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Layout\Tab;
use Atrium\Layout\Tabs;
use PHPUnit\Framework\TestCase;

/**
 * Tabs/Tab are layout containers (SCH-10): they build a tree, expose a stable
 * id for the active-tab state, and flatten to their leaf fields like any other
 * container — the active tab is purely a render-time concern.
 */
final class TabsTest extends TestCase
{
    public function testTabIdIsSluggedFromLabel(): void
    {
        self::assertSame('basic-info', Tab::make('Basic Info')->getId());
        self::assertSame('tab', Tab::make('—')->getId());
    }

    public function testTabsExposesItsTabsAndAStableId(): void
    {
        $tabs = Tabs::make()->tabs([
            Tab::make('Main'),
            Tab::make('Advanced'),
        ]);

        self::assertCount(2, $tabs->getTabs());
        self::assertContainsOnlyInstancesOf(Tab::class, $tabs->getTabs());

        $id = $tabs->getId();
        self::assertStringStartsWith('tabs-', $id);
        // Stable across calls and independent of later child mutation.
        self::assertSame($id, $tabs->getId());
    }

    public function testExplicitIdWins(): void
    {
        $tabs = Tabs::make()->id('settings')->tabs([Tab::make('A')]);

        self::assertSame('settings', $tabs->getId());
    }

    public function testFieldsFlattenThroughTabs(): void
    {
        $schema = (new Schema())->components([
            Tabs::make()->tabs([
                Tab::make('Main')->schema([TextField::make('title')]),
                Tab::make('Meta')->schema([TextField::make('slug'), TextField::make('author')]),
            ]),
        ]);

        $names = array_map(static fn ($field): string => $field->getName(), $schema->getFields());
        self::assertSame(['title', 'slug', 'author'], $names);
    }
}
