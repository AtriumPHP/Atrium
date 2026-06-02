<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Url;

/**
 * Single-line text input. Supports `email()` / `url()` shortcuts that set the
 * HTML input type and add the matching constraint.
 */
final class TextField extends Field
{
    private string $inputType = 'text';

    public function getType(): string
    {
        return 'text';
    }

    public function email(): self
    {
        $this->inputType = 'email';
        $this->constraints[] = new Email();

        return $this;
    }

    public function url(): self
    {
        $this->inputType = 'url';
        $this->constraints[] = new Url();

        return $this;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function normalize(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        $value = \is_scalar($value) ? trim((string) $value) : '';

        return '' === $value ? null : $value;
    }

    public function toFormValue(mixed $value): mixed
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
