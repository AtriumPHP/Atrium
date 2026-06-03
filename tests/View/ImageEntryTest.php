<?php

declare(strict_types=1);

namespace Atrium\Tests\View;

use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\ImageEntry;
use PHPUnit\Framework\TestCase;

final class ImageEntryTest extends TestCase
{
    public function testResolvesUrlFromState(): void
    {
        $view = ImageEntry::make('photo')->state('/img/p.png')->toView(new Tag());

        self::assertSame('/img/p.png', $view['src']);
        self::assertFalse($view['isEmpty']);
    }

    public function testFallsBackToDefaultImageUrl(): void
    {
        $view = ImageEntry::make('photo')->state(null)->defaultImageUrl('/img/fallback.png')->toView(new Tag());

        self::assertSame('/img/fallback.png', $view['src']);
        self::assertFalse($view['isEmpty']);
    }

    public function testEmptyWhenNoUrlAndNoDefault(): void
    {
        $view = ImageEntry::make('photo')->state(null)->toView(new Tag());

        self::assertTrue($view['isEmpty']);
        self::assertNull($view['src']);
    }

    public function testCircularAndSizing(): void
    {
        $view = ImageEntry::make('photo')->state('/p.png')->circular()->imageSize(48)->toView(new Tag());

        self::assertTrue($view['circular']);
        self::assertSame(48, $view['width']);
        self::assertSame(48, $view['height']);
    }

    public function testSquareUnsetsCircular(): void
    {
        $view = ImageEntry::make('photo')->state('/p.png')->circular()->square()->toView(new Tag());

        self::assertFalse($view['circular']);
    }

    public function testAltDefaultsToLabel(): void
    {
        $view = ImageEntry::make('photo')->label('Avatar')->state('/p.png')->toView(new Tag());

        self::assertSame('Avatar', $view['alt']);
    }

    public function testUnsafeSchemeIsDropped(): void
    {
        $view = ImageEntry::make('photo')->state('javascript:alert(1)')->toView(new Tag());

        self::assertTrue($view['isEmpty']);
        self::assertNull($view['src']);
    }
}
