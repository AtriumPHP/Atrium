<?php

declare(strict_types=1);

namespace Atrium\Dashboard;

/**
 * The single lookup point for registered dashboards.
 *
 * Dashboards are collected via the `atrium.dashboard` tag (autoconfigured from
 * {@see Dashboard}) and indexed by slug. Slug uniqueness *across dashboards and
 * resources* (PNL-07) is enforced where the panel navigation is built, since both
 * share the `/admin/{slug}` namespace.
 */
final class DashboardRegistry
{
    /** @var array<string, Dashboard> indexed by slug */
    private array $bySlug = [];

    /**
     * @param iterable<Dashboard> $dashboards
     */
    public function __construct(iterable $dashboards = [])
    {
        foreach ($dashboards as $dashboard) {
            $this->add($dashboard);
        }
    }

    public function add(Dashboard $dashboard): void
    {
        $slug = $dashboard->getSlug();

        if (isset($this->bySlug[$slug])) {
            throw new \LogicException(\sprintf('Duplicate dashboard slug "%s" for %s; it is already used by %s.', $slug, $dashboard::class, $this->bySlug[$slug]::class));
        }

        $this->bySlug[$slug] = $dashboard;
    }

    public function getBySlug(string $slug): Dashboard
    {
        return $this->bySlug[$slug]
            ?? throw new \InvalidArgumentException(\sprintf('No dashboard registered for slug "%s".', $slug));
    }

    public function hasSlug(string $slug): bool
    {
        return isset($this->bySlug[$slug]);
    }

    /**
     * @return list<Dashboard>
     */
    public function all(): array
    {
        return array_values($this->bySlug);
    }
}
