<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\RelationManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * REL-19: a relation that points at a dedicated RelationManagerConfiguration via
 * ->using() draws its table from that class, and the modal form applies its form().
 */
final class RelationManagerUsingTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testTableUsesTheDedicatedConfigClass(): void
    {
        $component = $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'using-post', 'parentId' => '1', 'relation' => 'comments', 'pathPrefix' => '/admin',
        ]);
        self::assertInstanceOf(RelationManager::class, $component->component());

        // The config's distinctive column header proves the table came from using().
        self::assertStringContainsString('Extracted Body', $component->render()->toString());
    }

    public function testModalFormUsesTheDedicatedConfigForm(): void
    {
        $component = $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'using-post', 'parentId' => '1', 'relation' => 'comments', 'pathPrefix' => '/admin',
        ]);
        $component->call('openCreate');

        // The modal hosts Atrium:Form; the config's form() relabels the field.
        self::assertStringContainsString('Extracted Comment', $component->render()->toString());
    }
}
