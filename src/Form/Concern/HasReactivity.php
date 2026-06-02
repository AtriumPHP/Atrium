<?php

declare(strict_types=1);

namespace Atrium\Form\Concern;

use Atrium\Form\Get;
use Atrium\Form\Set;

/**
 * Cross-field reactivity for a field (FRM-10).
 *
 * `afterStateUpdated()` registers a callback that runs — **server-side**, during
 * the Live Component re-render — whenever this field's value changes. The
 * callback receives the new state, a {@see Get} (read other fields) and a
 * {@see Set} (write other fields), e.g. to derive a slug from a title. Because a
 * callback is useless unless the field round-trips on change, registering one
 * implies {@see live()}.
 */
trait HasReactivity
{
    /** @var (\Closure(mixed, Get, Set): void)|null */
    private ?\Closure $afterStateUpdatedCallback = null;

    abstract public function live(bool $live = true): static;

    /**
     * @param \Closure(mixed, Get, Set): void $callback
     */
    public function afterStateUpdated(\Closure $callback): static
    {
        $this->afterStateUpdatedCallback = $callback;

        return $this->live();
    }

    public function hasAfterStateUpdated(): bool
    {
        return null !== $this->afterStateUpdatedCallback;
    }

    public function runAfterStateUpdated(mixed $state, Get $get, Set $set): void
    {
        if (null !== $this->afterStateUpdatedCallback) {
            ($this->afterStateUpdatedCallback)($state, $get, $set);
        }
    }
}
