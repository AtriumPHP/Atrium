<?php

declare(strict_types=1);

namespace Atrium\Form\Concern;

use Atrium\Form\Get;

/**
 * Conditional visibility for a field (FRM-08, FRM-09).
 *
 * Visibility is **server-evaluated** during the Live Component re-render that a
 * `->live()` field already triggers — there is no client-side branching. A
 * hidden field is neither rendered nor validated nor hydrated.
 *
 * - `visible()` / `hidden()` take a bool or a `\Closure(Get): bool`.
 * - `visibleOn()` / `hiddenOn()` gate on the current operation (`create`/`edit`).
 */
trait HasVisibility
{
    /** @var (\Closure(Get): bool)|null */
    private ?\Closure $visibilityCallback = null;

    /** @var list<string>|null */
    private ?array $visibleOn = null;

    /** @var list<string>|null */
    private ?array $hiddenOn = null;

    public function visible(bool|\Closure $condition = true): static
    {
        $this->visibilityCallback = $condition instanceof \Closure
            ? $condition
            : static fn (Get $get): bool => $condition;

        return $this;
    }

    public function hidden(bool|\Closure $condition = true): static
    {
        $this->visibilityCallback = $condition instanceof \Closure
            ? static fn (Get $get): bool => !$condition($get)
            : static fn (Get $get): bool => !$condition;

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

    public function isVisible(Get $get, string $operation): bool
    {
        if (null !== $this->hiddenOn && \in_array($operation, $this->hiddenOn, true)) {
            return false;
        }

        if (null !== $this->visibleOn && !\in_array($operation, $this->visibleOn, true)) {
            return false;
        }

        if (null !== $this->visibilityCallback && !($this->visibilityCallback)($get)) {
            return false;
        }

        return true;
    }
}
