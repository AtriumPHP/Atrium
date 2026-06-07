<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Controller\AdminController;
use Atrium\Dashboard\DashboardRegistry;
use Atrium\Relation\ParentRelationResolver;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Resource\ProjectResource;
use Atrium\Tests\Fixtures\Resource\TaskResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Twig\Environment;

/**
 * Record-scoped screens fail closed when no data provider is configured (a
 * no-Doctrine install): they can neither load nor authorize their record, so they
 * 404 rather than render unauthorized chrome. Create/list need no record and are
 * unaffected.
 */
final class NoDataProviderTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function controller(): AdminController
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $resources = new ResourceRegistry([new ProjectResource(), new TaskResource()]);

        return new AdminController(
            $twig,
            $resources,
            new DashboardRegistry([]),
            'Atrium',
            '/admin',
            new ParentRelationResolver($resources),
            PropertyAccess::createPropertyAccessor(),
            null, // no data provider
        );
    }

    public function testEditIs404WithoutADataProvider(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->edit('project', '1');
    }

    public function testViewIs404WithoutADataProvider(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->view('project', '1');
    }

    public function testNestedEditIs404WithoutADataProvider(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->nestedEdit('project', '1', 'task', '1');
    }

    public function testCreateStillRendersWithoutADataProvider(): void
    {
        // Create needs no record, so it remains available (regression guard for the
        // fail-closed change above).
        $response = $this->controller()->create('project');
        self::assertSame(200, $response->getStatusCode());
    }
}
