<?php

declare(strict_types=1);

namespace Atrium\Tests\Content;

use Atrium\Content\Image;
use Atrium\Content\Text;
use Atrium\Content\UnorderedList;
use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase
{
    public function testPlainTextDefaults(): void
    {
        $text = Text::make('Heads up');

        self::assertSame('Heads up', $text->getContent());
        self::assertFalse($text->isBadge());
        self::assertFalse($text->isHtml());
        self::assertSame('text-sm', $text->getTypographyClass());
        self::assertSame('text-gray-700 dark:text-gray-300', $text->getColorClass());
        self::assertSame([], $text->getChildComponents());
    }

    public function testStyledText(): void
    {
        $text = Text::make('Danger')->color('danger')->size('lg')->weight('bold');

        self::assertSame('text-lg font-bold', $text->getTypographyClass());
        self::assertSame('text-red-600 dark:text-red-400', $text->getColorClass());
    }

    public function testBadgeText(): void
    {
        $text = Text::make('New')->badge()->color('success');

        self::assertTrue($text->isBadge());
        self::assertSame('text-sm font-medium', $text->getTypographyClass());
        self::assertSame('bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300', $text->getColorClass());
    }

    public function testUnknownColourFallsBackToGray(): void
    {
        self::assertSame('text-gray-700 dark:text-gray-300', Text::make('x')->color('chartreuse')->getColorClass());
    }

    public function testUnorderedListItems(): void
    {
        $list = UnorderedList::make(['One', 'Two', 'Three']);

        self::assertSame(['One', 'Two', 'Three'], $list->getItems());
        self::assertSame('@Atrium/components/content/unordered_list.html.twig', $list->getTemplate());
    }

    public function testImageDimensionsAndAlignment(): void
    {
        $image = Image::make('/logo.png', 'Logo')->imageSize(48)->alignCenter();

        self::assertSame('/logo.png', $image->getUrl());
        self::assertSame('Logo', $image->getAlt());
        self::assertSame(48, $image->getWidth());
        self::assertSame(48, $image->getHeight());
        self::assertSame('mx-auto', $image->getAlignmentClass());
    }
}
