<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * Multi-line text input.
 */
class TextareaField extends Field
{
    private int $rows = 4;

    public function getType(): string
    {
        return 'textarea';
    }

    public function rows(int $rows): self
    {
        $this->rows = max(1, $rows);

        return $this;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function normalize(mixed $value): mixed
    {
        if (!\is_scalar($value)) {
            return null;
        }

        return '' === (string) $value ? null : (string) $value;
    }

    public function toFormValue(mixed $value): mixed
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
