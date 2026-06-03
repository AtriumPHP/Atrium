<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The Form component hosts and dispatches the create/edit screen's header actions
 * (PAG-05): server-driven, against the loaded record, with the confirm flow and a
 * generic redirect when the record falls out of scope after the action.
 */
final class FormHeaderActionsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testEditScreenRendersTheHeaderActions(): void
    {
        $html = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'header-action-tag',
            'entityId' => '2', // an active tag (in scope)
            'pathPrefix' => '/admin',
        ])->render()->toString();

        self::assertStringContainsString('Append bang', $html);
        self::assertStringContainsString('Archive', $html);
        self::assertStringContainsString('data-live-action-param="requestAction"', $html);
    }

    public function testCreateScreenHasNoHeaderActions(): void
    {
        $html = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'header-action-tag',
            'pathPrefix' => '/admin',
        ])->render()->toString();

        self::assertStringNotContainsString('Append bang', $html);
        self::assertStringNotContainsString('Archive', $html);
    }

    public function testServerDrivenActionConfirmsThenRedirectsWhenRecordLeavesScope(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'header-action-tag',
            'entityId' => '2',
            'pathPrefix' => '/admin',
        ]);

        // A confirmable action opens the prompt and runs nothing yet. The header
        // action trigger carries no id (it is subject-less); the Form acts on its
        // loaded entityId.
        $component->call('requestAction', ['name' => 'archive']);
        self::assertStringContainsString('Are you sure', $component->render()->toString());

        // Confirming archives the record (active = false); it is now out of the
        // resource's scope, so there is nothing left to edit and the form
        // redirects to the list.
        $component->call('confirmAction');
        self::assertTrue($component->response()->isRedirect('/admin/header-action-tag'));
    }
}
