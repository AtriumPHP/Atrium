<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\AtriumBundle;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Resource\TagResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class KernelBootTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AtriumTestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Symfony's ErrorHandler registers an exception handler on boot and does
        // not remove it on kernel shutdown; restore it so PHPUnit does not flag
        // the test as risky for leaking a handler.
        restore_exception_handler();
    }

    public function testKernelBootsWithTheAtriumBundleRegistered(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('AtriumBundle', $kernel->getBundles());
        self::assertInstanceOf(AtriumBundle::class, $kernel->getBundle('AtriumBundle'));
    }

    public function testResourceRegistryIsWiredAndPublic(): void
    {
        self::bootKernel();

        self::assertInstanceOf(ResourceRegistry::class, self::getContainer()->get(ResourceRegistry::class));
    }

    public function testResourcesAreAutoDiscoveredViaAutoconfiguration(): void
    {
        self::bootKernel();

        /** @var ResourceRegistry $registry */
        $registry = self::getContainer()->get(ResourceRegistry::class);

        self::assertTrue($registry->hasSlug('tag'), 'The tagged resource should be discovered.');
        self::assertInstanceOf(TagResource::class, $registry->getBySlug('tag'));
        self::assertTrue($registry->hasSlug('layout-tag'), 'Every tagged resource should be discovered.');
        self::assertCount(11, $registry->all());
    }

    public function testBundleConfigurationIsExposedAsParameters(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertSame('/admin', $container->getParameter('atrium.path_prefix'));
        self::assertSame('Atrium', $container->getParameter('atrium.brand'));
    }

    public function testAtriumTwigNamespaceRendersTheLayout(): void
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('@Atrium/admin/layout.html.twig', [
            'panel' => ['brand' => 'Atrium', 'pathPrefix' => '/admin', 'resources' => []],
        ]);

        self::assertStringContainsString('<title>Atrium</title>', $html);
    }
}
