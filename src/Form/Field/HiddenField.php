<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * A value carried in the form state but not shown in the layout. Its value lives
 * in `formData` (from a default or an `afterStateUpdated()` write) and is
 * validated/persisted like any field, but it renders no widget — so it never
 * takes a grid cell. Set values with `->default()` or from another field.
 */
class HiddenField extends Field
{
    public function getType(): string
    {
        return 'hidden';
    }

    public function rendersInLayout(): bool
    {
        return false;
    }
}
