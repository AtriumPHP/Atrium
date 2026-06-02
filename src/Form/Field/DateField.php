<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * Date input (`<input type="date">`), modelled as DateTimeImmutable.
 */
class DateField extends Field
{
    protected string $format = 'Y-m-d';

    public function getType(): string
    {
        return 'date';
    }

    public function normalize(mixed $value): mixed
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!'.$this->format, $value)
            ?: null;

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    public function toFormValue(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format($this->format) : '';
    }
}
