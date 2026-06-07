<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\StimulusBundle\AssetMapper\ControllersMapGenerator;

/**
 * The bundle ships its first Stimulus controller (toast timing/animation). Prove
 * StimulusBundle discovers it for an app that references the package in its
 * controllers.json — so the consumer enables it with one config line and no JS.
 */
final class NotificationsAssetTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testNotificationsControllerIsDiscovered(): void
    {
        self::bootKernel();
        // Service id verified against vendor/symfony/stimulus-bundle/config/services.php.
        // It's private, but the test container exposes private services.
        $generator = self::getContainer()->get('stimulus.asset_mapper.controllers_map_generator');
        self::assertInstanceOf(ControllersMapGenerator::class, $generator);

        // getControllersMap() returns array<string name, MappedControllerAsset>
        // (MappedControllerAsset has no ->name property), so assert on the key.
        self::assertArrayHasKey('atrium--notifications', $generator->getControllersMap());
    }
}
