<?php

declare(strict_types=1);

namespace Atrium\Tests\View;

use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\CodeEntry;
use PHPUnit\Framework\TestCase;

final class CodeEntryTest extends TestCase
{
    public function testRendersStringCode(): void
    {
        $view = CodeEntry::make('payload')->state('SELECT 1')->toView(new Tag());

        self::assertSame('SELECT 1', $view['code']);
        self::assertFalse($view['isEmpty']);
    }

    public function testLanguageLabel(): void
    {
        $view = CodeEntry::make('payload')->state('{}')->language('json')->toView(new Tag());

        self::assertSame('json', $view['language']);
    }

    public function testArrayStateIsPrettyJson(): void
    {
        $view = CodeEntry::make('payload')->state(['a' => 1])->toView(new Tag());

        self::assertIsString($view['code']);
        self::assertStringContainsString('"a": 1', $view['code']);
    }

    public function testEmptyState(): void
    {
        $view = CodeEntry::make('payload')->state(null)->placeholder('—')->toView(new Tag());

        self::assertTrue($view['isEmpty']);
        self::assertSame('—', $view['placeholder']);
    }

    public function testCopyable(): void
    {
        $view = CodeEntry::make('payload')->state('abc')->copyable()->toView(new Tag());

        self::assertTrue($view['copyable']);
        self::assertSame('abc', $view['copyValue']);
    }
}
