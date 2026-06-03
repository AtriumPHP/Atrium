<?php

declare(strict_types=1);

namespace Atrium\Dashboard;

/**
 * The built-in root dashboard (DSH-07).
 *
 * Rendered at `/admin` when no registered dashboard is accessible, it preserves
 * the panel's welcome screen — a greeting plus a card per registered resource.
 * It is a fallback, not a tagged service: register your own {@see Dashboard} and
 * it becomes the root screen instead.
 */
final class DefaultDashboard extends Dashboard
{
    /**
     * The slug of the panel root (`/admin`). Register a {@see Dashboard} returning
     * this from {@see Dashboard::getSlug()} to replace the welcome screen.
     */
    public const ROOT_SLUG = 'dashboard';

    public function getSlug(): string
    {
        return self::ROOT_SLUG;
    }

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function rendersResourceLinks(): bool
    {
        return true;
    }
}
