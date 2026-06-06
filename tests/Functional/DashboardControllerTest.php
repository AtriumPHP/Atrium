<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Controller\AdminController;
use Atrium\Dashboard\Dashboard;
use Atrium\Dashboard\DashboardRegistry;
use Atrium\Dashboard\DefaultDashboard;
use Atrium\Relation\ParentRelationResolver;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Resource\TagResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Twig\Environment;

/**
 * Exercises the controller's dashboard dispatch directly with hand-built
 * registries, so root-slug replacement and slug-collision detection can be tested
 * without polluting the shared test kernel.
 */
final class DashboardControllerTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRootSlugDashboardReplacesTheDefault(): void
    {
        $controller = $this->controller(
            new ResourceRegistry(),
            new DashboardRegistry([new RootHomeDashboard()]),
        );

        $html = (string) $controller->dashboard()->getContent();

        self::assertStringContainsString('Command Center', $html);
        // The built-in welcome is replaced, not shown alongside.
        self::assertStringNotContainsString('Welcome to', $html);
    }

    public function testDefaultDashboardServedAndListedWhenNoneDefined(): void
    {
        $controller = $this->controller(
            new ResourceRegistry(),
            new DashboardRegistry(),
        );

        $html = (string) $controller->dashboard()->getContent();

        self::assertStringContainsString('Welcome to', $html);
        // With no user dashboards, the built-in default appears in the sidebar.
        self::assertStringContainsString('flex-1 truncate">Dashboard</span>', $html);
    }

    public function testSlugCollisionBetweenDashboardAndResourceThrows(): void
    {
        $controller = $this->controller(
            new ResourceRegistry([new TagResource()]), // slug "tag"
            new DashboardRegistry([new CollidingDashboard()]), // slug "tag"
        );

        $this->expectException(\LogicException::class);
        $controller->dashboard();
    }

    private function controller(ResourceRegistry $resources, DashboardRegistry $dashboards): AdminController
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return new AdminController($twig, $resources, $dashboards, 'Atrium', '/admin', new ParentRelationResolver($resources), PropertyAccess::createPropertyAccessor(), null);
    }
}

final class RootHomeDashboard extends Dashboard
{
    public function getSlug(): string
    {
        return DefaultDashboard::ROOT_SLUG;
    }

    public function getTitle(): string
    {
        return 'Command Center';
    }
}

final class CollidingDashboard extends Dashboard
{
    public function getSlug(): string
    {
        return 'tag';
    }
}
