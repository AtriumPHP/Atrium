<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * A field edited as a variable-length list of rows (Tags, Key-value). The form
 * component adds/removes rows generically against the form state via this
 * contract, so the reactive add/remove plumbing lives in one place and any
 * future repeatable field opts in without touching the renderer (FLD-06/07).
 */
interface RepeatableField
{
    /**
     * A fresh, empty row appended when the user clicks "add" — a string for a
     * tag list, a `['key' => '', 'value' => '']` pair for a key-value map.
     */
    public function newRow(): mixed;

    /**
     * Coerce the current form state into the ordered list of rows to render
     * (one input group per row), tolerating the model shape too.
     *
     * @return list<mixed>
     */
    public function rows(mixed $state): array;
}
