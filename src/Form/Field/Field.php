<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Base class for form fields (FRM-01). Fluent and Doctrine-agnostic.
 *
 * A field knows its name, presentation flags and validation constraints, and
 * how to convert between the model value and the form (string) value. Concrete
 * types declare their widget via {@see getType()}.
 *
 * Part of the public API contract (PRD §9).
 *
 * @phpstan-consistent-constructor
 */
abstract class Field
{
    protected ?string $label = null;

    protected bool $required = false;

    protected bool $live = false;

    protected bool $disabled = false;

    protected ?string $helpText = null;

    protected mixed $default = null;

    /** @var list<Constraint> */
    protected array $constraints = [];

    protected function __construct(
        protected readonly string $name,
    ) {
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Mark the field as reactive: changing it triggers a server re-render so
     * dependent fields can update (FRM-05).
     */
    public function live(bool $live = true): static
    {
        $this->live = $live;

        return $this;
    }

    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function help(string $helpText): static
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Attach Symfony validation constraints (FRM-01, FRM-04).
     *
     * @param list<Constraint> $constraints
     */
    public function rules(array $constraints): static
    {
        $this->constraints = array_values($constraints);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name) ?? $this->name));
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function getHelp(): ?string
    {
        return $this->helpText;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Full constraint set, including an implicit NotBlank when required.
     *
     * @return list<Constraint>
     */
    public function getConstraints(): array
    {
        $constraints = $this->constraints;

        if ($this->required) {
            array_unshift($constraints, new NotBlank());
        }

        return $constraints;
    }

    /**
     * Semantic widget identifier (text, textarea, number, …). Used for grouping
     * and to derive the default {@see getTemplate()}.
     */
    abstract public function getType(): string;

    /**
     * The Twig template that renders this field's widget.
     *
     * Defaults to the built-in convention. Override in a custom field type to
     * ship your own template from any bundle — this is the extension point that
     * lets third-party apps add fields without touching the form renderer.
     */
    public function getTemplate(): string
    {
        return '@Atrium/components/form/widget/'.$this->getType().'.html.twig';
    }

    /**
     * Whether the widget renders its own <label> (e.g. an inline checkbox),
     * in which case the field wrapper does not render one.
     */
    public function rendersOwnLabel(): bool
    {
        return false;
    }

    /**
     * Convert a raw submitted (form) value into the model value.
     */
    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Convert a model value into the form (display) value.
     */
    public function toFormValue(mixed $value): mixed
    {
        return $value;
    }
}
