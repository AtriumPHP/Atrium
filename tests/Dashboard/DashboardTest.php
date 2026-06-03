<?php

declare(strict_types=1);

namespace Atrium\Tests\Dashboard;

use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DefaultDashboard;
use PHPUnit\Framework\TestCase;

final class DashboardTest extends TestCase
{
    public function testSlugIsDerivedFromClassNameWithoutDashboardSuffix(): void
    {
        $dashboard = new class extends Dashboard {};

        // Anonymous class short names are noisy, so test the derivation rules on
        // named subclasses below; here just assert it produces a non-empty slug.
        self::assertNotSame('', $dashboard->getSlug());
    }

    public function testNamedSlugDerivation(): void
    {
        self::assertSame('finance', (new FinanceDashboard())->getSlug());
        self::assertSame('sales-report', (new SalesReportDashboard())->getSlug());
    }

    public function testDefaults(): void
    {
        $dashboard = new FinanceDashboard();

        self::assertSame('Finance', $dashboard->getTitle());
        self::assertSame('Finance', $dashboard->getNavigationLabel());
        self::assertSame([], $dashboard->getWidgets());
        self::assertTrue($dashboard->canAccess());
        self::assertTrue($dashboard->shouldRegisterNavigation());
        self::assertNull($dashboard->getNavigationSort());
        self::assertFalse($dashboard->rendersResourceLinks());
    }

    public function testColumnsClassForInt(): void
    {
        self::assertSame('grid-cols-1 lg:grid-cols-2', (new FinanceDashboard())->getColumnsClass());
    }

    public function testColumnsClassForResponsiveMap(): void
    {
        $dashboard = new class extends Dashboard {
            /** @return array<string, int> */
            public function getColumns(): array
            {
                return ['md' => 2, 'xl' => 3];
            }
        };

        self::assertSame('grid-cols-1 md:grid-cols-2 xl:grid-cols-3', $dashboard->getColumnsClass());
    }

    public function testDefaultDashboardIsRootAndRendersResourceLinks(): void
    {
        $default = new DefaultDashboard();

        self::assertSame(DefaultDashboard::ROOT_SLUG, $default->getSlug());
        self::assertSame('dashboard', $default->getSlug());
        self::assertTrue($default->rendersResourceLinks());
    }
}

final class FinanceDashboard extends Dashboard
{
}

final class SalesReportDashboard extends Dashboard
{
}
