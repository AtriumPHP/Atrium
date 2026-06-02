<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A flexbox row (SCH-11): children sit side by side from the `from()` breakpoint
 * up and stack vertically below it. Unlike {@see Grid}, widths are content-driven
 * — each child fills the available space unless it opts out with `grow(false)`.
 */
final class Flex extends LayoutComponent
{
    private string $from = 'md';

    public static function make(): self
    {
        return new self();
    }

    /**
     * Tailwind breakpoint (`sm`/`md`/`lg`/`xl`/`2xl`) at which the row goes
     * horizontal; below it the children stack.
     */
    public function from(string $breakpoint): self
    {
        $this->from = $breakpoint;

        return $this;
    }

    public function getFromBreakpoint(): string
    {
        return $this->from;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/flex.html.twig';
    }
}
