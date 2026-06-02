<?php

declare(strict_types=1);

namespace Atrium\Form;

/**
 * A read accessor over the current form state (FRM-11), handed to field
 * callbacks (`visible()`, `optionsUsing()`, …) so they can react to other
 * fields without touching the raw `formData` array.
 *
 * Invoke it with a field name: `$get('country')`.
 */
final readonly class Get
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    public function __invoke(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
