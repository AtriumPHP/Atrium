<?php

declare(strict_types=1);

namespace Atrium\Tests\View;

use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\IconEntry;
use PHPUnit\Framework\TestCase;

final class IconEntryTest extends TestCase
{
    public function testBooleanRendersTrueTickInGreen(): void
    {
        $view = IconEntry::make('active')->boolean()->toView(new Tag(active: true));

        self::assertSame('check', $view['iconName']);
        self::assertFalse($view['isEmpty']);
        self::assertIsString($view['iconColorClass']);
        self::assertStringContainsString('green', $view['iconColorClass']);
    }

    public function testBooleanRendersFalseTickInRed(): void
    {
        $view = IconEntry::make('active')->boolean()->toView(new Tag(active: false));

        self::assertSame('x', $view['iconName']);
        self::assertIsString($view['iconColorClass']);
        self::assertStringContainsString('red', $view['iconColorClass']);
    }

    public function testBooleanAcceptsCustomIcons(): void
    {
        $view = IconEntry::make('active')->boolean('thumbs-up', 'thumbs-down')->toView(new Tag(active: true));

        self::assertSame('thumbs-up', $view['iconName']);
    }

    public function testMapsStateToIconViaClosure(): void
    {
        $entry = IconEntry::make('kind')
            ->icon(static fn (?string $state): string => 'a' === $state ? 'star' : 'circle');

        self::assertSame('star', $entry->toView(new Tag(kind: 'a'))['iconName']);
        self::assertSame('circle', $entry->toView(new Tag(kind: 'b'))['iconName']);
    }

    public function testExplicitColourOverridesBooleanDefault(): void
    {
        $view = IconEntry::make('active')->boolean()->color('info')->toView(new Tag(active: false));

        self::assertIsString($view['iconColorClass']);
        self::assertStringContainsString('sky', $view['iconColorClass']);
    }

    public function testSizeClass(): void
    {
        $view = IconEntry::make('active')->boolean()->size('xl')->toView(new Tag(active: true));

        self::assertSame('h-8 w-8', $view['sizeClass']);
    }

    public function testEmptyWhenNoIconResolves(): void
    {
        $view = IconEntry::make('kind')->icon(static fn (): ?string => null)->toView(new Tag());

        self::assertTrue($view['isEmpty']);
        self::assertNull($view['iconName']);
    }
}
