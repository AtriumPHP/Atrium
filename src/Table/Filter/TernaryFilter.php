<?php

declare(strict_types=1);

namespace Atrium\Table\Filter;

/**
 * A three-state filter over a boolean field: all (no filter), true or false.
 * Rendered as a select; values are `''` (all), `'1'` (true) and `'0'` (false).
 */
final class TernaryFilter extends Filter
{
    private string $placeholder = 'All';

    private string $trueLabel = 'Yes';

    private string $falseLabel = 'No';

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function labels(string $true, string $false): self
    {
        $this->trueLabel = $true;
        $this->falseLabel = $false;

        return $this;
    }

    public function conditions(string $value): array
    {
        return match ($value) {
            '1' => [$this->getField() => true],
            '0' => [$this->getField() => false],
            default => [],
        };
    }

    public function toView(string $value): array
    {
        return [
            'template' => $this->getTemplate(),
            'name' => $this->name,
            'label' => $this->getLabel(),
            'value' => \in_array($value, ['0', '1'], true) ? $value : '',
            'options' => [
                ['value' => '', 'label' => $this->placeholder],
                ['value' => '1', 'label' => $this->trueLabel],
                ['value' => '0', 'label' => $this->falseLabel],
            ],
        ];
    }
}
