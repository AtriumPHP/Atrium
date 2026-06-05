<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Twig\Components\RelationManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class RelationManagerActionsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function manager(string $parentId = '1', string $screen = 'edit', string $resource = 'post'): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => $resource,
            'parentId' => $parentId,
            'relation' => 'comments',
            'pathPrefix' => '/admin',
            'screen' => $screen,
        ]);
    }

    public function testDeleteRefusesAChildOfAnotherParent(): void
    {
        // Comment 3 belongs to post 2; the manager is mounted for post 1.
        $component = $this->manager('1');
        $component->call('requestAction', ['name' => 'delete', 'id' => '3']);

        self::assertNull($this->state($component)->confirmingAction);
        self::assertCount(0, $this->writer()->deleted);
    }

    public function testDeletesAChildOfThisParent(): void
    {
        $component = $this->manager('1');
        $component->call('requestAction', ['name' => 'delete', 'id' => '1']);
        self::assertSame('delete', $this->state($component)->confirmingAction);

        $component->call('confirmAction');
        self::assertCount(1, $this->writer()->deleted);
    }

    public function testDissociateUnlinksTheChildFromThisParent(): void
    {
        $component = $this->manager('1');
        self::assertStringContainsString('Great post', $component->render()->toString());

        $component->call('requestAction', ['name' => 'dissociate', 'id' => '1']);
        self::assertSame('dissociate', $this->state($component)->confirmingAction);

        $component->call('confirmAction');

        // Comment 1's postId is now null, so it no longer belongs to post 1…
        $html = $component->render()->toString();
        self::assertStringNotContainsString('Great post', $html);
        // …while its sibling (comment 2) is untouched. The record was not deleted.
        self::assertStringContainsString('Thanks for sharing', $html);
        self::assertCount(0, $this->writer()->deleted);
    }

    public function testDissociateHiddenAndRefusedWhenParentDenies(): void
    {
        $component = $this->manager('1', 'edit', 'post-deny');
        self::assertStringNotContainsString('Detach', $component->render()->toString());

        $component->call('requestAction', ['name' => 'dissociate', 'id' => '1']);
        self::assertNull($this->state($component)->confirmingAction);
    }

    public function testOpenCreateShowsAModalFormAndSavedClosesIt(): void
    {
        $component = $this->manager('1');

        $component->call('openCreate');
        self::assertSame('create', $this->state($component)->modalMode);
        self::assertStringContainsString('Atrium:Form', $component->render()->toString());

        // The nested Form emits relation:saved on save; the manager closes the modal.
        $component->emit('relation:saved');
        self::assertNull($this->state($component)->modalMode);
    }

    public function testOpenCreateIsRefusedOnAReadOnlyViewScreen(): void
    {
        $component = $this->manager('1', 'view');
        $component->call('openCreate');

        self::assertNull($this->state($component)->modalMode);
    }

    private function state(TestLiveComponent $component): RelationManager
    {
        $manager = $component->component();
        self::assertInstanceOf(RelationManager::class, $manager);

        return $manager;
    }

    private function writer(): ArrayDataWriter
    {
        $writer = self::getContainer()->get(ArrayDataWriter::class);
        \assert($writer instanceof ArrayDataWriter);

        return $writer;
    }
}
