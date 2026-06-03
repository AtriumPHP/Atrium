<?php

declare(strict_types=1);

namespace Atrium\Twig;

use Symfony\UX\Icons\Exception\IconNotFoundException;
use Symfony\UX\Icons\IconRendererInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Renders panel icons through Symfony UX Icons.
 *
 * Bare names (e.g. `cube`) resolve to the bundle's shipped Lucide set — the
 * `atrium` icon set registered in {@see \Atrium\AtriumBundle::prependExtension()}.
 * Already-namespaced names (e.g. `lucide:rocket`, `mdi:home`, or a consumer's
 * own set) are passed through untouched, so integrators can use any Iconify
 * icon for navigation and actions without leaving the panel's conventions.
 *
 * @internal
 */
final class IconExtension extends AbstractExtension
{
    public function __construct(
        private readonly IconRendererInterface $iconRenderer,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('atrium_icon', $this->renderIcon(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Generic glyph rendered when a requested icon cannot be found — keeps a
     * mistyped navigation or action icon from taking the whole panel down.
     */
    private const FALLBACK = 'atrium:squares';

    /**
     * @param array<string, bool|string> $attributes
     */
    public function renderIcon(string $name, array $attributes = []): string
    {
        if (!str_contains($name, ':')) {
            $name = 'atrium:'.$name;
        }

        try {
            return $this->iconRenderer->renderIcon($name, $attributes);
        } catch (IconNotFoundException) {
            return $this->iconRenderer->renderIcon(self::FALLBACK, $attributes);
        }
    }
}
