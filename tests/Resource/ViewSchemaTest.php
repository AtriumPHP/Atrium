<?php

declare(strict_types=1);

namespace Atrium\Tests\Resource;

use Atrium\Layout\Section;
use Atrium\Tests\Fixtures\Resource\ViewFallbackTagResource;
use Atrium\Tests\Fixtures\Resource\ViewTagResource;
use Atrium\View\TextEntry;
use PHPUnit\Framework\TestCase;

final class ViewSchemaTest extends TestCase
{
    public function testUsesTheDeclaredViewSchema(): void
    {
        $components = (new ViewTagResource())->resolveViewSchema()->getComponents();

        self::assertCount(1, $components);
        $section = $components[0];
        self::assertInstanceOf(Section::class, $section);
        self::assertCount(4, $section->getChildComponents());
        self::assertContainsOnlyInstancesOf(TextEntry::class, $section->getChildComponents());
    }

    public function testFallsBackToFormFieldsAsReadOnlyEntries(): void
    {
        $components = (new ViewFallbackTagResource())->resolveViewSchema()->getComponents();

        self::assertCount(2, $components);
        self::assertContainsOnlyInstancesOf(TextEntry::class, $components);

        $first = $components[0];
        self::assertInstanceOf(TextEntry::class, $first);
        self::assertSame('name', $first->getName());
    }
}
