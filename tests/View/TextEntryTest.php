<?php

declare(strict_types=1);

namespace Atrium\Tests\View;

use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\TextEntry;
use PHPUnit\Framework\TestCase;

final class TextEntryTest extends TestCase
{
    public function testResolvesStateFromRecordByName(): void
    {
        $view = TextEntry::make('name')->toView(new Tag(name: 'Hello'));

        self::assertSame('Hello', $view['value']);
        self::assertFalse($view['isEmpty']);
        self::assertTrue($view['visible']);
    }

    public function testHumanisesLabelIncludingDottedPaths(): void
    {
        self::assertSame('Name', TextEntry::make('name')->getLabel());
        self::assertSame('Created at', TextEntry::make('createdAt')->getLabel());
        self::assertSame('Name', TextEntry::make('author.name')->getLabel());
        self::assertSame('Custom', TextEntry::make('name')->label('Custom')->getLabel());
    }

    public function testGetStateUsingOverridesTheName(): void
    {
        $view = TextEntry::make('whatever')
            ->getStateUsing(static fn (Tag $tag): string => 'computed:'.$tag->name)
            ->toView(new Tag(name: 'x'));

        self::assertSame('computed:x', $view['value']);
    }

    public function testFormatStateUsing(): void
    {
        $view = TextEntry::make('active')
            ->formatStateUsing(static fn (mixed $state): string => $state ? 'Active' : 'Inactive')
            ->toView(new Tag(active: false));

        self::assertSame('Inactive', $view['value']);
    }

    public function testPlaceholderWhenStateIsEmpty(): void
    {
        $view = TextEntry::make('kind')->placeholder('not set')->toView(new Tag(kind: null));

        self::assertTrue($view['isEmpty']);
        self::assertSame('not set', $view['placeholder']);
    }

    public function testBadgeProducesAColourClass(): void
    {
        $view = TextEntry::make('active')->badge()->color('success')->toView(new Tag(active: true));

        self::assertTrue($view['isBadge']);
        self::assertIsString($view['colorClass']);
        self::assertStringContainsString('green', $view['colorClass']);
    }

    public function testRecordAwareVisibility(): void
    {
        $entry = TextEntry::make('name')->visible(static fn (Tag $tag): bool => $tag->active);

        self::assertFalse($entry->toView(new Tag(active: false))['visible']);
        self::assertTrue($entry->toView(new Tag(active: true))['visible']);
    }

    public function testRecordAwareUrl(): void
    {
        $view = TextEntry::make('name')
            ->url(static fn (Tag $tag): string => '/tags/'.$tag->slug)
            ->toView(new Tag(slug: 'seven'));

        self::assertSame('/tags/seven', $view['url']);
    }

    public function testMoneyFormatting(): void
    {
        $view = TextEntry::make('price')->state(123456)->money('USD', divideBy: 100)->toView(new Tag());

        self::assertIsString($view['value']);
        self::assertStringContainsString('1,234.56', $view['value']);
    }

    public function testDateFormatting(): void
    {
        $view = TextEntry::make('createdAt')
            ->state(new \DateTimeImmutable('2026-01-02 13:45:00'))
            ->dateTime('Y-m-d')
            ->toView(new Tag());

        self::assertSame('2026-01-02', $view['value']);
    }

    public function testLimitTruncation(): void
    {
        $view = TextEntry::make('x')->state('abcdefghij')->limit(4)->toView(new Tag());

        self::assertSame('abcd…', $view['value']);
    }

    public function testListFromSeparatedString(): void
    {
        $view = TextEntry::make('x')->state('a, b, c')->separator(',')->bulleted()->toView(new Tag());

        self::assertSame(['a', 'b', 'c'], $view['items']);
        self::assertTrue($view['bulleted']);
    }
}
