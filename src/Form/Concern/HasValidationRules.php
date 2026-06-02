<?php

declare(strict_types=1);

namespace Atrium\Form\Concern;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Fluent validation helpers (FRM-12) that compile to Symfony constraints, so
 * common rules read fluently and are IDE-discoverable instead of
 * `rules([new Length(max: 50)])`. `rules([...])` remains the escape hatch.
 */
trait HasValidationRules
{
    abstract protected function addConstraint(Constraint $constraint): static;

    public function maxLength(int $max): static
    {
        return $this->addConstraint(new Length(max: max(1, $max)));
    }

    public function minLength(int $min): static
    {
        return $this->addConstraint(new Length(min: max(0, $min)));
    }

    public function length(int $length): static
    {
        $length = max(1, $length);

        return $this->addConstraint(new Length(min: $length, max: $length));
    }

    public function regex(string $pattern, ?string $message = null): static
    {
        return $this->addConstraint(
            null === $message ? new Regex($pattern) : new Regex($pattern, $message),
        );
    }
}
