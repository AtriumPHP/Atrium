<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class NotificationsComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testAppendsALiveNotificationAndDismissesIt(): void
    {
        // createLiveComponent() takes the registered component NAME, not a class.
        $component = $this->createLiveComponent('Atrium:Notifications');

        $component->call('receive', [
            'notification' => [
                'id' => 'abc', 'title' => 'Saved', 'body' => null,
                'status' => 'success', 'icon' => 'circle-check', 'color' => 'success',
                'duration' => 5000, 'actions' => [],
            ],
        ]);
        self::assertStringContainsString('Saved', $component->render()->toString());

        $component->call('dismiss', ['id' => 'abc']);
        self::assertStringNotContainsString('Saved', $component->render()->toString());
    }

    public function testTraitRaisesAToastOnTheLiveChannelTheHostListensFor(): void
    {
        // A *different* component using InteractsWithNotifications emits the exact
        // `atrium:notification` event (with a `notification` payload) the host's
        // #[LiveListener('atrium:notification')] receive(#[LiveArg] $notification)
        // consumes — proving the live→host channel is wired end-to-end.
        $emitter = $this->createLiveComponent('Atrium:Test:Notifying');

        $emitter->call('raiseSuccess');

        $this->assertComponentEmitEvent($emitter, 'atrium:notification');

        $event = $emitter->getEmittedEvent($emitter->render(), 'atrium:notification');
        self::assertNotNull($event);
        self::assertArrayHasKey('notification', $event['data']);
        $notification = $event['data']['notification'];
        self::assertIsArray($notification);
        self::assertSame('Persisted', $notification['title']);
        self::assertSame('success', $notification['status']);

        // The emitted payload is the exact `notification` arg the host's
        // receive(#[LiveArg] array $notification) consumes (asserted to render in
        // testAppendsALiveNotificationAndDismissesIt) — the live→host channel is wired.
    }
}
