<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

use Atrium\Form\Concern\HasPlaceholder;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

/**
 * Numeric input. Defaults to float; call `integer()` for whole numbers.
 */
class NumberField extends Field
{
    use HasPlaceholder;

    private bool $integer = false;

    public function getType(): string
    {
        return 'number';
    }

    public function integer(bool $integer = true): self
    {
        $this->integer = $integer;

        return $this;
    }

    public function min(int|float $min): static
    {
        return $this->addConstraint(new GreaterThanOrEqual($min));
    }

    public function max(int|float $max): static
    {
        return $this->addConstraint(new LessThanOrEqual($max));
    }

    public function isInteger(): bool
    {
        return $this->integer;
    }

    public function normalize(mixed $value): mixed
    {
        if (null === $value || '' === $value || !is_numeric($value)) {
            return null;
        }

        return $this->integer ? (int) $value : (float) $value;
    }

    public function toFormValue(mixed $value): mixed
    {
        return is_numeric($value) ? (string) $value : '';
    }
}
