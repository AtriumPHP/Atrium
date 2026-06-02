<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * A radio-button group. Reuses {@see SelectField}'s option machinery (static
 * options or `optionsUsing()`), rendered as a vertical list of radios.
 */
class RadioField extends SelectField
{
    public function getType(): string
    {
        return 'radio';
    }
}
