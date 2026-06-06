<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\RelationManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * A relation manager whose target is a nested resource (REL-18): rows link to the
 * child's nested pages and "New" links to nested create, instead of inline modals.
 */
final class RelationManagerNestedTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function manager(): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'project',
            'parentId' => '1',
            'relation' => 'tasks',
            'pathPrefix' => '/admin',
        ]);
    }

    private function state(TestLiveComponent $component): RelationManager
    {
        $manager = $component->component();
        self::assertInstanceOf(RelationManager::class, $manager);

        return $manager;
    }

    public function testRowsLinkToNestedChildPagesAndNewLinksToNestedCreate(): void
    {
        $html = $this->manager()->render()->toString();

        self::assertStringContainsString('/admin/project/1/task/1', $html);    // a row → nested view
        self::assertStringContainsString('/admin/project/1/task/new', $html);  // New → nested create
        // Inline create modal trigger is gone in nested mode.
        self::assertStringNotContainsString('data-live-action-param="openCreate"', $html);
    }

    public function testOpenCreateIsANoOpInNestedMode(): void
    {
        $component = $this->manager();
        $component->call('openCreate');
        self::assertNull($this->state($component)->modalMode);
    }
}
