<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Resource\ViewTagResource;
use Atrium\Twig\Components\RecordActions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * The View screen's header actions are hosted by the RecordActions Live Component:
 * the Edit link renders as an anchor and Delete runs through the shared
 * server-driven confirm → delete flow — even though the View screen is static.
 */
final class RecordActionsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
        ViewTagResource::reset();
    }

    public function testRendersTheEditLinkAndDeleteServerButton(): void
    {
        $html = $this->actions()->render()->toString();

        // Edit is a plain link to the edit screen (resolved from the record context).
        self::assertStringContainsString('href="/admin/view-tag/3/edit"', $html);
        // Delete is a server action (a requestAction trigger), not a link.
        self::assertStringContainsString('data-live-action-param="requestAction"', $html);
        self::assertStringContainsString('data-live-name-param="delete"', $html);
    }

    public function testDeleteAsksForConfirmationThenDeletes(): void
    {
        $component = $this->actions();

        // The standalone Delete button carries no id; the host runs it against its
        // own entityId. Requesting it opens the prompt without deleting.
        $component->call('requestAction', ['name' => 'delete']);
        self::assertSame('delete', $this->component($component)->confirmingAction);
        self::assertStringContainsString('Are you sure', $component->render()->toString());
        self::assertSame([], $this->writer()->deleted);

        // Confirming runs the delete through the writer and clears the prompt.
        $component->call('confirmAction');
        self::assertNull($this->component($component)->confirmingAction);
        self::assertCount(1, $this->writer()->deleted);
    }

    private function actions(): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RecordActions', [
            'resource' => 'view-tag',
            'entityId' => '3',
            'pathPrefix' => '/admin',
        ]);
    }

    private function writer(): ArrayDataWriter
    {
        $writer = self::getContainer()->get(ArrayDataWriter::class);
        self::assertInstanceOf(ArrayDataWriter::class, $writer);

        return $writer;
    }

    private function component(TestLiveComponent $component): RecordActions
    {
        $instance = $component->component();
        self::assertInstanceOf(RecordActions::class, $instance);

        return $instance;
    }
}
