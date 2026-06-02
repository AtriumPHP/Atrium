<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * A colour picker (`<input type="color">`), modelled as a hex string.
 */
class ColorField extends Field
{
    public function getType(): string
    {
        return 'color';
    }

    public function normalize(mixed $value): mixed
    {
        if (!\is_scalar($value) || '' === (string) $value) {
            return null;
        }

        return (string) $value;
    }

    public function toFormValue(mixed $value): mixed
    {
        return \is_scalar($value) && '' !== (string) $value ? (string) $value : '#000000';
    }
}
