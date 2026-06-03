<?php

declare(strict_types=1);

namespace Atrium\Tests\Dashboard;

use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardRegistry;
use Atrium\Tests\Fixtures\Dashboard\ForbiddenDashboard;
use Atrium\Tests\Fixtures\Dashboard\InsightsDashboard;
use PHPUnit\Framework\TestCase;

final class DashboardRegistryTest extends TestCase
{
    public function testIndexesBySlug(): void
    {
        $insights = new InsightsDashboard();
        $registry = new DashboardRegistry([$insights]);

        self::assertTrue($registry->hasSlug('insights'));
        self::assertSame($insights, $registry->getBySlug('insights'));
        self::assertSame([$insights], $registry->all());
    }

    public function testUnknownSlugThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new DashboardRegistry())->getBySlug('missing');
    }

    public function testDuplicateSlugThrows(): void
    {
        $this->expectException(\LogicException::class);

        new DashboardRegistry([
            new ForbiddenDashboard(),
            new class extends Dashboard {
                public function getSlug(): string
                {
                    return 'forbidden';
                }
            },
        ]);
    }
}
