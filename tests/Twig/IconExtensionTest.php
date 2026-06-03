<?php

declare(strict_types=1);

namespace Atrium\Tests\Twig;

use Atrium\Twig\IconExtension;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Icons\Exception\IconNotFoundException;
use Symfony\UX\Icons\IconRendererInterface;

final class IconExtensionTest extends TestCase
{
    public function testBareNameResolvesToTheBuiltInAtriumSet(): void
    {
        $renderer = new class implements IconRendererInterface {
            public ?string $lastName = null;

            /** @var array<string, bool|string> */
            public array $lastAttributes = [];

            public function renderIcon(string $name, array $attributes = []): string
            {
                $this->lastName = $name;
                $this->lastAttributes = $attributes;

                return '<svg></svg>';
            }
        };

        (new IconExtension($renderer))->renderIcon('cube', ['class' => 'h-5 w-5']);

        self::assertSame('atrium:cube', $renderer->lastName);
        self::assertSame(['class' => 'h-5 w-5'], $renderer->lastAttributes);
    }

    public function testNamespacedNameIsPassedThroughUntouched(): void
    {
        $renderer = new class implements IconRendererInterface {
            public ?string $lastName = null;

            public function renderIcon(string $name, array $attributes = []): string
            {
                $this->lastName = $name;

                return '<svg></svg>';
            }
        };

        (new IconExtension($renderer))->renderIcon('lucide:rocket');

        self::assertSame('lucide:rocket', $renderer->lastName);
    }

    public function testUnknownIconDegradesToTheFallbackGlyph(): void
    {
        $renderer = new class implements IconRendererInterface {
            public ?string $lastName = null;

            public function renderIcon(string $name, array $attributes = []): string
            {
                $this->lastName = $name;

                if ('atrium:squares' !== $name) {
                    throw new IconNotFoundException($name);
                }

                return '<svg data-fallback></svg>';
            }
        };

        $html = (new IconExtension($renderer))->renderIcon('does-not-exist');

        self::assertSame('atrium:squares', $renderer->lastName);
        self::assertStringContainsString('data-fallback', $html);
    }
}
