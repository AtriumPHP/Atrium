<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

/**
 * In-memory {@see DataWriterInterface} adapter, pairing with
 * {@see ArrayDataProvider} for tests, fixtures and demos.
 */
final class ArrayDataWriter implements DataWriterInterface
{
    /** @var list<object> entities passed to create(), in order — for assertions */
    public array $created = [];

    /** @var list<object> entities passed to delete(), in order — for assertions */
    public array $deleted = [];

    /**
     * @param array<class-string, list<object>> $records records indexed by entity class
     */
    public function __construct(
        public array $records = [],
    ) {
    }

    public function create(object $entity): void
    {
        $this->created[] = $entity;
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
        $this->deleted[] = $entity;
        $class = $entity::class;
        $this->records[$class] = array_values(array_filter(
            $this->records[$class] ?? [],
            static fn (object $candidate): bool => $candidate !== $entity,
        ));
    }

    public function transactional(callable $work): mixed
    {
        // No real transaction for the in-memory backend — just run the work.
        return $work();
    }

    private function contains(object $entity): bool
    {
        return \in_array($entity, $this->records[$entity::class] ?? [], true);
    }
}
