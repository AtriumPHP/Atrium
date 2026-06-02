<?php

declare(strict_types=1);

namespace Atrium\Form;

/**
 * A write accessor over the current form state (FRM-11), handed to
 * `afterStateUpdated()` callbacks so they can update other fields (e.g. derive a
 * slug from a title): `$set('slug', '…')`.
 *
 * Backed by a closure the Form component supplies, so writes land on the live
 * `formData` and are reflected on the next render.
 */
final class Set
{
    /** @var \Closure(string, mixed): void */
    private \Closure $setter;

    /**
     * @param \Closure(string, mixed): void $setter
     */
    public function __construct(\Closure $setter)
    {
        $this->setter = $setter;
    }

    public function __invoke(string $key, mixed $value): void
    {
        ($this->setter)($key, $value);
    }
}
