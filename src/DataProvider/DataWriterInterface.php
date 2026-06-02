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
}
