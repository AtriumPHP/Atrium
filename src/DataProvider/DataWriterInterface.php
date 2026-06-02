<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

/**
 * Backend-agnostic write abstraction (DAT-03).
 *
 * Persistence is kept behind this interface so create/update/delete work the
 * same way regardless of the storage backend (Doctrine ORM by default). The
 * Form component and actions write exclusively through it.
 */
interface DataWriterInterface
{
    public function create(object $entity): void;

    public function update(object $entity): void;

    public function delete(object $entity): void;

    /**
     * Run $work inside a transaction, committing on success and rolling back if
     * it throws (re-throwing the exception). Atrium wraps a save (and a delete)
     * in this so the lifecycle hooks around persistence are atomic: if an
     * `afterSave`/`afterDelete` fails, the write is undone rather than half-done.
     *
     * Backends without transactions (e.g. the in-memory array writer) just run
     * the work. The closure's return value is passed through.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed;
}
