<?php

declare(strict_types=1);

namespace Atrium\Layout\Concern;

/**
 * Flex-grow placement for a {@see \Atrium\Layout\Component} inside a
 * {@see \Atrium\Layout\Flex} row. Ignored outside a Flex parent.
 *
 * By default a flex child grows to share the available space (`flex-1`);
 * `grow(false)` keeps it at its content width (`flex-none`) so siblings expand
 * around it.
 */
trait HasGrow
{
    private ?bool $grow = null;

    public function grow(bool $grow = true): static
    {
        $this->grow = $grow;

        return $this;
    }

    public function getGrow(): ?bool
    {
        return $this->grow;
    }

    public function getGrowClass(): string
    {
        return false === $this->grow ? 'flex-none' : 'flex-1';
    }
}
