<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * Boolean checkbox / toggle.
 */
class CheckboxField extends Field
{
    public function getType(): string
    {
        return 'checkbox';
    }

    public function rendersOwnLabel(): bool
    {
        return true;
    }

    public function normalize(mixed $value): mixed
    {
        return \in_array($value, [true, '1', 1, 'on', 'true'], true);
    }

    public function toFormValue(mixed $value): mixed
    {
        return (bool) $value;
    }

    /**
     * A required checkbox must be explicitly true; the base NotBlank handling is
     * unhelpful for booleans, so checkboxes never contribute an implicit one.
     *
     * @return list<\Symfony\Component\Validator\Constraint>
     */
    public function getConstraints(): array
    {
        if (!$this->required) {
            return $this->constraints;
        }

        return [new \Symfony\Component\Validator\Constraints\IsTrue(), ...$this->constraints];
    }
}
