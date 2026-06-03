<?php

declare(strict_types=1);

namespace Atrium\Dashboard;

use Atrium\Widget\Widget;

/**
 * Base class for a dashboard — a routable panel page that composes
 * {@see Widget}s into a responsive grid (DSH-01).
 *
 * Like a {@see \Atrium\Page\Page}, a dashboard is a controller/descriptor class,
 * NOT a Live Component: the reactive widgets it lays out are the Live Components.
 * Subclasses are autoconfigured via the `atrium.dashboard` tag and resolved by
 * slug through {@see DashboardRegistry}; an app may register several. The root
 * screen (`/admin`) renders the accessible dashboard with the lowest navigation
 * sort, falling back to the built-in {@see DefaultDashboard}.
 *
 * This signature is part of the public API contract — treat changes as
 * BC-relevant.
 */
abstract class Dashboard
{
    /**
     * URL-friendly identifier, used for routing (`/admin/{slug}`) and registry
     * lookups. Defaults to a kebab-case form of the class short name with a
     * trailing "Dashboard" dropped (e.g. `FinanceDashboard` → `finance`).
     */
    public function getSlug(): string
    {
        $short = (new \ReflectionClass($this))->getShortName();
        $short = preg_replace('/Dashboard$/', '', $short);
        $short = null === $short || '' === $short ? 'dashboard' : $short;
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $short) ?? $short;

        return strtolower($kebab);
    }

    /**
     * Page heading and default navigation label.
     */
    public function getTitle(): string
    {
        return ucfirst(str_replace('-', ' ', $this->getSlug()));
    }

    /**
     * The widgets to render, in order. Each entry is a widget descriptor class.
     *
     * @return list<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * Grid columns for the widget layout. An int is N columns at `lg`+ (one on
     * small screens); a per-breakpoint map (e.g. `['md' => 2, 'xl' => 3]`) gives
     * responsive control.
     *
     * @return int|array<string, int>
     */
    public function getColumns(): int|array
    {
        return 2;
    }

    /**
     * Whether the dashboard is reachable. Gates both the navigation entry and the
     * page (the controller returns 403). Defaults to open; override to integrate
     * the host app's security (it may use the dashboard's injected services).
     */
    public function canAccess(): bool
    {
        return true;
    }

    public function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function getNavigationLabel(): string
    {
        return $this->getTitle();
    }

    public function getNavigationIcon(): ?string
    {
        return 'dashboard';
    }

    public function getNavigationGroup(): ?string
    {
        return null;
    }

    /**
     * Sort weight for the navigation entry — lower comes first, merged with the
     * resources. Entries without a weight (null) sort after weighted ones, in
     * registration order. The accessible dashboard sorting first is the panel's
     * root screen (`/admin`).
     */
    public function getNavigationSort(): ?int
    {
        return null;
    }

    public function getNavigationBadge(): ?string
    {
        return null;
    }

    public function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    /**
     * Whether this dashboard also renders the built-in resource links (the
     * welcome screen). Only the {@see DefaultDashboard} does.
     *
     * @internal
     */
    public function rendersResourceLinks(): bool
    {
        return false;
    }

    /**
     * Tailwind grid-template-columns classes for the widget grid.
     *
     * @internal
     */
    public function getColumnsClass(): string
    {
        $columns = $this->getColumns();

        if (\is_int($columns)) {
            return 1 >= $columns ? 'grid-cols-1' : 'grid-cols-1 lg:grid-cols-'.$columns;
        }

        $classes = ['grid-cols-1'];
        foreach ($columns as $breakpoint => $count) {
            $classes[] = $breakpoint.':grid-cols-'.$count;
        }

        return implode(' ', $classes);
    }
}
