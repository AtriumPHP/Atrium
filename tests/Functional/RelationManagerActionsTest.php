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

    private function manager(string $parentId = '1', string $screen = 'edit'): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RelationManager', [
            'resource' => 'post',
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
