<?php

declare(strict_types=1);

namespace Atrium\View;

/**
 * Renders the record value as a **colour swatch** beside its textual value — for a
 * stored brand/theme colour. The state is a CSS colour string (`#1d4ed8`,
 * `rgb(…)`, `hsl(…)` or a CSS named colour); an unrecognised value is treated as
 * empty and shows the `placeholder()`.
 *
 * ```php
 * ColorEntry::make('brandColor')->copyable();
 * ```
 */
final class ColorEntry extends Entry
{
    private bool $copyable = false;
    private ?string $copyMessage = null;

    public function copyable(bool $copyable = true, ?string $message = null): static
    {
        $this->copyable = $copyable;
        $this->copyMessage = $message;

        return $this;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/view/color.html.twig';
    }

    protected function viewExtras(mixed $state, object $record): array
    {
        $formatted = $this->applyFormatter($state, $record);
        $value = \is_string($formatted) ? trim($formatted) : '';
        $color = self::safeColor($value);

        return [
            'color' => $color,
            'value' => $value,
            'isEmpty' => null === $color,
            'copyable' => $this->copyable,
            'copyValue' => $value,
            'copyMessage' => $this->copyMessage,
        ];
    }

    /**
     * Accept only a CSS colour we can safely interpolate into an inline style:
     * a #hex (3/4/6/8 digits), an `rgb()/rgba()/hsl()/hsla()` function, or a
     * plain CSS named colour. Anything else (so no `;`, `}`, `url(...)`, …) is
     * rejected so the value can never break out of the `background-color` rule.
     */
    private static function safeColor(string $value): ?string
    {
        if ('' === $value) {
            return null;
        }

        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
            return $value;
        }

        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/]+\)$/i', $value)) {
            return $value;
        }

        return preg_match('/^[a-z]+$/i', $value) ? $value : null;
    }
}
