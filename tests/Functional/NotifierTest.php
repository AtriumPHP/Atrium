<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Notification\Notification;
use Atrium\Notification\NotificationAction;
use Atrium\Notification\Notifier;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class NotifierTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testSendPushesOntoTheFlashBagUnderTheSharedKey(): void
    {
        self::bootKernel();
        $requestStack = new RequestStack();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        $notifier = new Notifier($requestStack);
        $notifier->send(Notification::make('x')->title('Saved')->success());

        $flashed = $session->getFlashBag()->peek(Notifier::FLASH_KEY);
        self::assertCount(1, $flashed);
        self::assertIsArray($flashed[0]);
        self::assertSame('Saved', $flashed[0]['title']);
    }

    public function testFlashingDropsEmitActionsButKeepsLinks(): void
    {
        // PRD §8: emit-actions are live-channel-only; the flash bridge keeps link
        // actions only (an emit button can't meaningfully survive a redirect).
        $requestStack = new RequestStack();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        (new Notifier($requestStack))->send(
            Notification::make('y')->title('Saved')->actions([
                NotificationAction::make('View')->url('/admin/x/1'),
                NotificationAction::make('Undo')->emit('x:undo'),
            ]),
        );

        $flashed = $session->getFlashBag()->peek(Notifier::FLASH_KEY);
        self::assertIsArray($flashed[0]);
        self::assertIsArray($flashed[0]['actions']);
        self::assertCount(1, $flashed[0]['actions']);
        self::assertIsArray($flashed[0]['actions'][0]);
        self::assertSame('link', $flashed[0]['actions'][0]['kind']);
    }

    public function testSendIsANoOpWithoutASession(): void
    {
        $notifier = new Notifier(new RequestStack());
        $notifier->send(Notification::make('x')->title('Saved'));
        $this->expectNotToPerformAssertions();
    }
}
