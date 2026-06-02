<?php

declare(strict_types=1);

namespace Atrium\Form;

use Atrium\Form\Field\Field;
use Atrium\Layout\Component;

/**
 * A form schema: a tree of {@see Component}s — fields and layout containers
 * (Grid, Section, Fieldset) — configured in PHP (FRM-01, SCH-01).
 *
 * Small resources stay flat with {@see fields()}; richer ones nest layout via
 * {@see components()}. Either way {@see getFields()} flattens the tree to the
 * leaf fields the Form component hydrates, validates and persists, while
 * {@see getComponents()} exposes the tree for rendering. Pure configuration —
 * reusable and unit-testable in isolation.
 */
final class Schema
{
    /** @var list<Component> */
    private array $components = [];

    /**
     * Flat list of fields — the simple path for small resources.
     *
     * @param list<Field> $fields
     */
    public function fields(array $fields): self
    {
        $this->components = array_values($fields);

        return $this;
    }

    /**
     * Mixed tree of fields and layout containers (SCH-02).
     *
     * @param list<Component> $components
     */
    public function components(array $components): self
    {
        $this->components = array_values($components);

        return $this;
    }

    /**
     * Top-level components, for rendering.
     *
     * @return list<Component>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * All fields, flattened depth-first across nested layout (SCH-07).
     *
     * @return list<Field>
     */
    public function getFields(): array
    {
        return self::collectFields($this->components);
    }

    public function getField(string $name): ?Field
    {
        foreach ($this->getFields() as $field) {
            if ($field->getName() === $name) {
                return $field;
            }
        }

        return null;
    }

    public function hasField(string $name): bool
    {
        return null !== $this->getField($name);
    }

    /**
     * @param list<Component> $components
     *
     * @return list<Field>
     */
    private static function collectFields(array $components): array
    {
        $fields = [];

        foreach ($components as $component) {
            if ($component instanceof Field) {
                $fields[] = $component;

                continue;
            }

            foreach (self::collectFields($component->getChildComponents()) as $nested) {
                $fields[] = $nested;
            }
        }

        return $fields;
    }
}
