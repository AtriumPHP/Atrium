<?php

declare(strict_types=1);

namespace Atrium\Form;

use Atrium\Form\Field\Field;

/**
 * An ordered collection of form fields (FRM-01).
 *
 * Mirrors the table's column API: a resource (or a dedicated `Schemas/*` class)
 * configures a schema in PHP, and the Form Live Component renders and persists
 * it. Pure configuration — reusable and unit-testable in isolation.
 */
final class Schema
{
    /** @var list<Field> */
    private array $fields = [];

    /**
     * @param list<Field> $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $name): ?Field
    {
        foreach ($this->fields as $field) {
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
}
