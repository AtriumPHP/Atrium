<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Entity\Comment;
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

        // Exactly the parent's own child (comment 1) was handed to the writer.
        self::assertCount(1, $this->writer()->deleted);
        $deleted = $this->writer()->deleted[0];
        self::assertInstanceOf(Comment::class, $deleted);
        self::assertSame(1, $deleted->id);
        self::assertSame(1, $deleted->postId);
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

    public function testAssociateLinksAnExistingFreeRecord(): void
    {
        $component = $this->manager('1');
        $component->call('openAssociate');
        self::assertSame('associate', $this->state($component)->modalMode);

        // Comment 100 has no parent yet; attach it to post 1.
        $component->set('associateId', '100');
        $component->call('submitAssociate');

        self::assertNull($this->state($component)->modalMode);   // closed on success
        $html = $component->render()->toString();
        self::assertStringContainsString('Unassigned A', $html); // now listed under post 1
    }

    public function testSubmitAssociateIsANoOpWithoutASelection(): void
    {
        $component = $this->manager('1');
        $component->call('openAssociate');
        $component->call('submitAssociate');

        // Still open (nothing picked); no exception.
        self::assertSame('associate', $this->state($component)->modalMode);
    }

    public function testSubmitAssociateIsRefusedWhenParentDeniesAssociate(): void
    {
        // The picker is reachable (canAssociate needs a child, so it can't gate the
        // button), but the execution path must refuse when canAssociate is false.
        $component = $this->manager('1', 'edit', 'post-deny-assoc');
        $component->call('openAssociate');
        $component->set('associateId', '100');   // a genuinely linkable record
        $component->call('submitAssociate');

        // Comment 100 stays unlinked: it is not listed under the parent.
        $html = $this->manager('1', 'edit', 'post-deny-assoc')->render()->toString();
        self::assertStringNotContainsString('Unassigned A', $html);
    }

    public function testViewScreenManagerIsReadOnly(): void
    {
        $html = $this->manager('1', 'view')->render()->toString();

        // No mutating affordances on a read-only view manager…
        self::assertStringNotContainsString('Detach', $html);
        self::assertStringNotContainsString('Delete', $html);
        self::assertStringNotContainsString('New Comment', $html);
        self::assertStringNotContainsString('Attach existing', $html);
        // …but the related rows still render.
        self::assertStringContainsString('Great post', $html);
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
