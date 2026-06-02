<?php

declare(strict_types=1);

namespace Atrium\Layout\Concern;

use Atrium\Layout\StateAccessor;

/**
 * Conditional visibility for a schema component — a field or a layout container
 * (FRM-08, FRM-09).
 *
 * Visibility is **server-evaluated** during the Live Component re-render. A
 * hidden node is neither rendered nor (for fields) validated/persisted; a hidden
 * container drops its whole subtree.
 *
 * - `visible()` / `hidden()` take a bool or a `\Closure(StateAccessor): bool`.
 * - `visibleOn()` / `hiddenOn()` gate on the current operation (`create`/`edit`).
 */
trait HasVisibility
{
    /** @var (\Closure(StateAccessor): bool)|null */
    private ?\Closure $visibilityCallback = null;

    /** @var list<string>|null */
    private ?array $visibleOn = null;

    /** @var list<string>|null */
    private ?array $hiddenOn = null;

    public function visible(bool|\Closure $condition = true): static
    {
        $this->visibilityCallback = $condition instanceof \Closure
            ? $condition
            : static fn (StateAccessor $state): bool => $condition;

        return $this;
    }

    public function hidden(bool|\Closure $condition = true): static
    {
        $this->visibilityCallback = $condition instanceof \Closure
            ? static fn (StateAccessor $state): bool => !$condition($state)
            : static fn (StateAccessor $state): bool => !$condition;

        return $this;
    }

    /**
     * @param string|list<string> $operations
     */
    public function visibleOn(string|array $operations): static
    {
        $this->visibleOn = array_values((array) $operations);

        return $this;
    }

    /**
     * @param string|list<string> $operations
     */
    public function hiddenOn(string|array $operations): static
    {
        $this->hiddenOn = array_values((array) $operations);

        return $this;
    }

    public function isVisible(StateAccessor $state, string $operation): bool
    {
        if (null !== $this->hiddenOn && \in_array($operation, $this->hiddenOn, true)) {
            return false;
        }

        if (null !== $this->visibleOn && !\in_array($operation, $this->visibleOn, true)) {
            return false;
        }

        if (null !== $this->visibilityCallback && !($this->visibilityCallback)($state)) {
            return false;
        }

        return true;
    }
}
