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
    /**
     * Cross-field comparison rules (FRM-12). Evaluated by the form against the
     * full submitted state — a Symfony constraint can't see a sibling field.
     *
     * @var list<array{field: string, type: 'same'|'different', message: ?string}>
     */
    private array $comparisons = [];

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

    /**
     * Require this field to equal another field's value (e.g. password
     * confirmation). Evaluated server-side by the form against the full state.
     */
    public function same(string $field, ?string $message = null): static
    {
        $this->comparisons[] = ['field' => $field, 'type' => 'same', 'message' => $message];

        return $this;
    }

    /**
     * Require this field to differ from another field's value.
     */
    public function different(string $field, ?string $message = null): static
    {
        $this->comparisons[] = ['field' => $field, 'type' => 'different', 'message' => $message];

        return $this;
    }

    /**
     * @return list<array{field: string, type: 'same'|'different', message: ?string}>
     */
    public function getComparisons(): array
    {
        return $this->comparisons;
    }
}
