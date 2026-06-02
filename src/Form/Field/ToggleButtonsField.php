<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * A single-choice segmented control — the same options as a {@see SelectField},
 * rendered as a row of connected buttons.
 */
class ToggleButtonsField extends SelectField
{
    public function getType(): string
    {
        return 'toggle_buttons';
    }
}
