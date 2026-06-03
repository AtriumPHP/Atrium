<?php

declare(strict_types=1);

namespace Atrium\Tests\View;

use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\ColorEntry;
use PHPUnit\Framework\TestCase;

final class ColorEntryTest extends TestCase
{
    public function testAcceptsHexColour(): void
    {
        $view = ColorEntry::make('brand')->state('#1d4ed8')->toView(new Tag());

        self::assertSame('#1d4ed8', $view['color']);
        self::assertSame('#1d4ed8', $view['value']);
        self::assertFalse($view['isEmpty']);
    }

    public function testAcceptsRgbAndNamedColours(): void
    {
        self::assertSame('rgb(10, 20, 30)', ColorEntry::make('c')->state('rgb(10, 20, 30)')->toView(new Tag())['color']);
        self::assertSame('rebeccapurple', ColorEntry::make('c')->state('rebeccapurple')->toView(new Tag())['color']);
    }

    public function testRejectsUnsafeValue(): void
    {
        $view = ColorEntry::make('c')->state('red; } body { display:none')->toView(new Tag());

        self::assertNull($view['color']);
        self::assertTrue($view['isEmpty']);
    }

    public function testEmptyState(): void
    {
        $view = ColorEntry::make('c')->state(null)->placeholder('none')->toView(new Tag());

        self::assertTrue($view['isEmpty']);
        self::assertSame('none', $view['placeholder']);
    }

    public function testCopyable(): void
    {
        $view = ColorEntry::make('c')->state('#fff')->copyable(message: 'Copied!')->toView(new Tag());

        self::assertTrue($view['copyable']);
        self::assertSame('#fff', $view['copyValue']);
        self::assertSame('Copied!', $view['copyMessage']);
    }
}
