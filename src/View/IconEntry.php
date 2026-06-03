<?php

declare(strict_types=1);

namespace Atrium\View;

use Atrium\View\Concern\ResolvesColor;

/**
 * Renders the record value **as an icon** rather than text: map the state to an
 * icon name (and optional semantic colour), or use {@see boolean()} for the common
 * true/false tick. Empty state falls back to the entry's `placeholder()`.
 *
 * ```php
 * IconEntry::make('status')
 *     ->icon(fn (string $s) => match ($s) { 'active' => 'check', default => 'minus' })
 *     ->color(fn (string $s) => $s === 'active' ? 'success' : 'gray');
 *
 * IconEntry::make('verified')->boolean();   // check (green) / x (red)
 * ```
 *
 * The displayed icon reuses the base {@see Entry::icon()} option (record-aware),
 * so the value *is* the icon. Colour shares the panel-wide semantic palette.
 */
final class IconEntry extends Entry
{
    use ResolvesColor;

    private string|\Closure|null $color = null;
    private string $size = 'md';

    /**
     * Map the value to a tick: a true-ish state shows `$trueIcon`, otherwise
     * `$falseIcon`, coloured green/red unless an explicit {@see color()} is set.
     */
    public function boolean(string $trueIcon = 'check', string $falseIcon = 'x'): static
    {
        $this->icon(static fn (mixed $state): string => $state ? $trueIcon : $falseIcon);

        if (null === $this->color) {
            $this->color = static fn (mixed $state): string => $state ? 'success' : 'danger';
        }

        return $this;
    }

    public function color(string|\Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * One of: sm, md, lg, xl.
     */
    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/view/icon.html.twig';
    }

    protected function viewExtras(mixed $state, object $record): array
    {
        $icon = $this->icon instanceof \Closure ? ($this->icon)($state, $record) : $this->icon;
        $name = \is_string($icon) && '' !== $icon ? $icon : null;
        $scale = $this->colorScale($this->color, $state, $record);

        return [
            'iconName' => $name,
            'isEmpty' => null === $name,
            'iconColorClass' => match (true) {
                null === $scale, 'gray' === $scale => 'text-gray-600 dark:text-gray-400',
                default => \sprintf('text-%1$s-600 dark:text-%1$s-400', $scale),
            },
            'sizeClass' => $this->sizeClass(),
        ];
    }

    private function sizeClass(): string
    {
        return match ($this->size) {
            'sm' => 'h-4 w-4',
            'lg' => 'h-6 w-6',
            'xl' => 'h-8 w-8',
            default => 'h-5 w-5',
        };
    }
}
