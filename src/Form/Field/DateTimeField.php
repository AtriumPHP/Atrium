<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * Date-and-time input (`<input type="datetime-local">`), modelled as
 * DateTimeImmutable.
 */
final class DateTimeField extends DateField
{
    protected string $format = 'Y-m-d\TH:i';

    public function getType(): string
    {
        return 'datetime';
    }
}
