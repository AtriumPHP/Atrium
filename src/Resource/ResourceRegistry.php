<?php

declare(strict_types=1);

namespace Atrium\Resource;

/**
 * The single lookup point for registered resources.
 *
 * Resources are collected via the `atrium.resource` tag (autoconfigured from
 * {@see AdminResource}) and indexed by both slug and class name.
 */
final class ResourceRegistry
{
    /** @var array<string, AdminResource> indexed by slug */
    private array $bySlug = [];

    /** @var array<class-string<AdminResource>, AdminResource> indexed by class */
    private array $byClass = [];

    /**
     * @param iterable<AdminResource> $resources
     */
    public function __construct(iterable $resources = [])
    {
        foreach ($resources as $resource) {
            $this->add($resource);
        }
    }

    public function add(AdminResource $resource): void
    {
        $slug = $resource->getSlug();

        if (isset($this->bySlug[$slug])) {
            throw new \LogicException(\sprintf('Duplicate resource slug "%s" for %s; it is already used by %s.', $slug, $resource::class, $this->bySlug[$slug]::class));
        }

        $this->bySlug[$slug] = $resource;
        $this->byClass[$resource::class] = $resource;
    }

    public function getBySlug(string $slug): AdminResource
    {
        return $this->bySlug[$slug]
            ?? throw new \InvalidArgumentException(\sprintf('No resource registered for slug "%s".', $slug));
    }

    /**
     * @param class-string<AdminResource> $class
     */
    public function getByClass(string $class): AdminResource
    {
        return $this->byClass[$class]
            ?? throw new \InvalidArgumentException(\sprintf('No resource registered for class "%s".', $class));
    }

    public function hasSlug(string $slug): bool
    {
        return isset($this->bySlug[$slug]);
    }

    /**
     * @return list<AdminResource>
     */
    public function all(): array
    {
        return array_values($this->bySlug);
    }
}
