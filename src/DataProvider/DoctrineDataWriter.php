<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Default {@see DataWriterInterface} adapter, backed by Doctrine ORM.
 */
final readonly class DoctrineDataWriter implements DataWriterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function delete(object $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
