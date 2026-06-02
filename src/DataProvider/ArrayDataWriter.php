<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

/**
 * In-memory {@see DataWriterInterface} adapter, pairing with
 * {@see ArrayDataProvider} for tests, fixtures and demos.
 */
final class ArrayDataWriter implements DataWriterInterface
{
    /**
     * @param array<class-string, list<object>> $records records indexed by entity class
     */
    public function __construct(
        public array $records = [],
    ) {
    }

    public function create(object $entity): void
    {
        $this->records[$entity::class][] = $entity;
    }

    public function update(object $entity): void
    {
        if (!$this->contains($entity)) {
            $this->create($entity);
        }
    }

    public function delete(object $entity): void
    {
        $class = $entity::class;
        $this->records[$class] = array_values(array_filter(
            $this->records[$class] ?? [],
            static fn (object $candidate): bool => $candidate !== $entity,
        ));
    }

    private function contains(object $entity): bool
    {
        return \in_array($entity, $this->records[$entity::class] ?? [], true);
    }
}
