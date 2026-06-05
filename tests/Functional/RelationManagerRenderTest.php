<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * End-to-end proof of the relations foundation: a parent resource's relation
 * manager, mounted through the container, lists only that parent's related rows
 * (REL-04, REL-09) — resolving the descriptor via the real registry/resolver and
 * reading through the {@see \Atrium\DataProvider\RelationDataProvider}.
 */
final class RelationManagerRenderTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRelationManagerListsOnlyTheParentsRelatedRows(): void
    {
        $component = $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'post',
            'parentId' => '1',
            'relation' => 'comments',
            'pathPrefix' => '/admin',
        ]);

        $html = $component->render()->toString();

        // Post 1's comments are listed…
        self::assertStringContainsString('Great post', $html);
        self::assertStringContainsString('Thanks for sharing', $html);
        // …and Post 2's comment is not.
        self::assertStringNotContainsString('On the other post', $html);
    }

    public function testRelationManagerScopesToADifferentParent(): void
    {
        $component = $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'post',
            'parentId' => '2',
            'relation' => 'comments',
            'pathPrefix' => '/admin',
        ]);

        $html = $component->render()->toString();

        self::assertStringContainsString('On the other post', $html);
        self::assertStringNotContainsString('Great post', $html);
    }
}
