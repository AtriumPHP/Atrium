<?php

declare(strict_types=1);

namespace Atrium\Dashboard;

/**
 * Base class for a dashboard — a routable panel page that composes
 * {@see \Atrium\Widget\Widget}s into a responsive layout (DSH-01).
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
     * Configure the dashboard's layout (DSH-08) — the widgets it shows and how
     * they are arranged. Mirrors {@see \Atrium\Resource\AdminResource::table()}
     * and `form()`: the framework hands you a {@see DashboardConfiguration}, you
     * populate it and return it.
     *
     * The simple path is `$dashboard->widgets([A::class, B::class])`; richer
     * dashboards nest {@see WidgetSlot}s inside layout containers via
     * `$dashboard->schema([...])`. Returns it unchanged (an empty dashboard) by
     * default.
     */
    public function dashboard(DashboardConfiguration $dashboard): DashboardConfiguration
    {
        return $dashboard;
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
}
