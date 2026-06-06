<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\RelationManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Many-to-many relation manager (REL-M3): Attach / Detach through a pivot, with
 * the action set dispatched by the relation kind.
 */
final class RelationManagerManyToManyTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function manager(string $resource = 'post-tags'): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => $resource,
            'parentId' => '1',
            'relation' => 'tags',
            'pathPrefix' => '/admin',
        ]);
    }

    private function state(TestLiveComponent $component): RelationManager
    {
        $manager = $component->component();
        self::assertInstanceOf(RelationManager::class, $manager);

        return $manager;
    }

    public function testManyToManyManagerShowsAttachAndDetachButNotOwnedActions(): void
    {
        $html = $this->manager()->render()->toString();

        self::assertStringContainsString('Tag 01', $html);            // a linked tag is listed
        self::assertStringContainsString('Attach existing', $html);
        self::assertStringContainsString('Detach', $html);
        self::assertStringNotContainsString('Delete', $html);         // no owned delete on M:N
        self::assertStringNotContainsString('New Tag', $html);        // no owned create on M:N
    }

    public function testDetachUnlinksTheTag(): void
    {
        $component = $this->manager();
        self::assertStringContainsString('Tag 01', $component->render()->toString());

        $component->call('requestAction', ['name' => 'detach', 'id' => '1']);
        self::assertSame('detach', $this->state($component)->confirmingAction);

        $component->call('confirmAction');
        self::assertStringNotContainsString('Tag 01', $component->render()->toString());
    }

    public function testAttachLinksATagWithPivotData(): void
    {
        $component = $this->manager();
        $component->call('openAttach');
        self::assertSame('attach', $this->state($component)->modalMode);

        // Tag 03 is not yet linked to post 1.
        $component->set('attachId', '3');
        $component->set('pivotData', ['note' => 'primary']);
        $component->call('submitAttach');

        self::assertNull($this->state($component)->modalMode);
        self::assertStringContainsString('Tag 03', $component->render()->toString());
    }

    public function testAttachIsRefusedWhenParentDenies(): void
    {
        $component = $this->manager('post-tags-restricted');
        self::assertStringNotContainsString('Detach', $component->render()->toString());

        $component->call('openAttach');
        $component->set('attachId', '3');
        $component->call('submitAttach');

        // Still nothing linked beyond the seed; tag 3 was not attached.
        self::assertStringNotContainsString('Tag 03', $this->manager('post-tags-restricted')->render()->toString());
    }

    public function testDetachIsRefusedWhenParentDenies(): void
    {
        $component = $this->manager('post-tags-restricted');
        $component->call('requestAction', ['name' => 'detach', 'id' => '1']);

        self::assertNull($this->state($component)->confirmingAction);
    }

    public function testOneToManyManagerStillShowsDissociateAndDeleteAfterKindDispatch(): void
    {
        // Regression guard: the 1:M `comments` manager keeps its REL-M2 action set.
        $component = $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'post', 'parentId' => '1', 'relation' => 'comments', 'pathPrefix' => '/admin',
        ]);
        $html = $component->render()->toString();

        self::assertStringContainsString('Detach', $html);   // dissociate label
        self::assertStringContainsString('Delete', $html);   // owned delete
        self::assertStringContainsString('New Comment', $html);
    }
}
