<?php

declare(strict_types=1);

namespace Atrium\Tests\View;

use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\KeyValueEntry;
use PHPUnit\Framework\TestCase;

final class KeyValueEntryTest extends TestCase
{
    public function testRendersArrayAsRows(): void
    {
        $view = KeyValueEntry::make('meta')->toView(new Tag(meta: ['region' => 'EU', 'tier' => 'gold']));

        self::assertSame(
            [['key' => 'region', 'value' => 'EU'], ['key' => 'tier', 'value' => 'gold']],
            $view['rows'],
        );
        self::assertFalse($view['isEmpty']);
    }

    public function testDecodesJsonString(): void
    {
        $view = KeyValueEntry::make('meta')->state('{"a":1,"b":true}')->toView(new Tag());

        self::assertSame(
            [['key' => 'a', 'value' => '1'], ['key' => 'b', 'value' => 'Yes']],
            $view['rows'],
        );
    }

    public function testEmptyArray(): void
    {
        $view = KeyValueEntry::make('meta')->placeholder('No metadata')->toView(new Tag(meta: []));

        self::assertTrue($view['isEmpty']);
        self::assertSame('No metadata', $view['placeholder']);
    }

    public function testCustomColumnLabels(): void
    {
        $view = KeyValueEntry::make('meta')->keyLabel('Field')->valueLabel('Setting')->toView(new Tag(meta: ['x' => 'y']));

        self::assertSame('Field', $view['keyLabel']);
        self::assertSame('Setting', $view['valueLabel']);
    }
}
