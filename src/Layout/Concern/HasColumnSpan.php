<?php

declare(strict_types=1);

namespace Atrium\Layout\Concern;

/**
 * Column-span placement for a {@see \Atrium\Layout\Component} within its parent
 * grid. Shared by fields and layout containers.
 *
 * `columnSpan(int)` spans N columns at the `lg` breakpoint and above (the same
 * breakpoint the default grid activates at); `columnSpanFull()` always spans the
 * full width. Responsive arrays are a future extension.
 */
trait HasColumnSpan
{
    private int|string|null $columnSpan = null;

    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = $span;

        return $this;
    }

    public function columnSpanFull(): static
    {
        $this->columnSpan = 'full';

        return $this;
    }

    public function getColumnSpan(): int|string|null
    {
        return $this->columnSpan;
    }

    public function getColumnSpanClass(): string
    {
        return match (true) {
            null === $this->columnSpan => '',
            'full' === $this->columnSpan => 'col-span-full',
            default => 'lg:col-span-'.$this->columnSpan,
        };
    }
}
