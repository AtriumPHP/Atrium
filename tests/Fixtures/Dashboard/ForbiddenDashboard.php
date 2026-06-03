<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Dashboard;

use Atrium\Dashboard\Dashboard;

/**
 * A dashboard the viewer may never reach — 403 on navigation, hidden from the
 * menu.
 */
final class ForbiddenDashboard extends Dashboard
{
    public function canAccess(): bool
    {
        return false;
    }
}
