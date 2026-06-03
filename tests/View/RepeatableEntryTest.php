<?php

declare(strict_types=1);

namespace Atrium\Tests\View;

use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\RepeatableEntry;
use Atrium\View\TextEntry;
use PHPUnit\Framework\TestCase;

final class RepeatableEntryTest extends TestCase
{
    public function testResolvesItemsFromAnArrayOfObjects(): void
    {
        $a = (object) ['name' => 'A'];
        $b = (object) ['name' => 'B'];

        $view = RepeatableEntry::make('items')->state([$a, $b])->toView(new Tag());

        self::assertSame([$a, $b], $view['items']);
        self::assertFalse($view['isEmpty']);
    }

    public function testResolvesItemsFromATraversable(): void
    {
        $items = new \ArrayIterator([(object) ['x' => 1], (object) ['x' => 2]]);

        $view = RepeatableEntry::make('items')->state($items)->toView(new Tag());

        self::assertIsArray($view['items']);
        self::assertCount(2, $view['items']);
    }

    public function testCastsAssociativeArrayItemsToObjects(): void
    {
        $view = RepeatableEntry::make('items')
            ->state([['name' => 'Small'], ['name' => 'Large']])
            ->toView(new Tag());

        $items = $view['items'];
        self::assertIsArray($items);
        self::assertContainsOnlyInstancesOf(\stdClass::class, $items);
        $first = $items[0];
        self::assertInstanceOf(\stdClass::class, $first);
        self::assertSame('Small', $first->name);
    }

    public function testDropsScalarItems(): void
    {
        $view = RepeatableEntry::make('items')->state(['scalar', 42, (object) ['ok' => true]])->toView(new Tag());

        self::assertIsArray($view['items']);
        self::assertCount(1, $view['items']);
    }

    public function testEmptyWhenStateIsNotAList(): void
    {
        $view = RepeatableEntry::make('items')->state(null)->placeholder('No items')->toView(new Tag());

        self::assertTrue($view['isEmpty']);
        self::assertSame('No items', $view['placeholder']);
    }

    public function testNestedSchemaIsExposed(): void
    {
        $entry = RepeatableEntry::make('items')->schema([
            TextEntry::make('name'),
            TextEntry::make('qty'),
        ]);

        self::assertCount(2, $entry->getSchemaComponents());
    }

    public function testGridAndColumnClasses(): void
    {
        $view = RepeatableEntry::make('items')->grid(2)->columns(3)->toView(new Tag());

        // The blocks gallery engages at `sm`; the within-block columns at `lg`.
        self::assertSame('grid-cols-1 sm:grid-cols-2', $view['gridClass']);
        self::assertSame('grid-cols-1 lg:grid-cols-3', $view['columnsClass']);
    }

    public function testContainedDefaultsTrueAndIsToggleable(): void
    {
        self::assertTrue(RepeatableEntry::make('items')->toView(new Tag())['contained']);
        self::assertFalse(RepeatableEntry::make('items')->contained(false)->toView(new Tag())['contained']);
    }
}
