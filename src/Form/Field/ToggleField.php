<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * A boolean toggle switch — a {@see CheckboxField} with switch styling. Shares
 * the checkbox's boolean normalisation, inline label and `IsTrue`-when-required
 * behaviour.
 */
class ToggleField extends CheckboxField
{
    public function getType(): string
    {
        return 'toggle';
    }
}
