<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class NestedRoutingTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function routeName(string $path): string
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $match = $router->match($path);
        self::assertIsString($match['_route']);

        return $match['_route'];
    }

    public function testNestedIndexMatches(): void
    {
        self::assertSame('atrium_nested_index', $this->routeName('/admin/project/1/task'));
    }

    public function testNestedCreateMatchesBeforeBareId(): void
    {
        self::assertSame('atrium_nested_create', $this->routeName('/admin/project/1/task/new'));
    }

    public function testNestedEditMatches(): void
    {
        self::assertSame('atrium_nested_edit', $this->routeName('/admin/project/1/task/7/edit'));
    }

    public function testNestedViewMatches(): void
    {
        self::assertSame('atrium_nested_view', $this->routeName('/admin/project/1/task/7'));
    }

    public function testFlatRoutesAreUnaffected(): void
    {
        self::assertSame('atrium_resource_view', $this->routeName('/admin/project/1'));
        self::assertSame('atrium_resource_edit', $this->routeName('/admin/project/1/edit'));
        self::assertSame('atrium_resource_create', $this->routeName('/admin/project/new'));
        self::assertSame('atrium_page', $this->routeName('/admin/project'));
    }
}
