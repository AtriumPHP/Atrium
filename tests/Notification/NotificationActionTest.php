<?php

declare(strict_types=1);

namespace Atrium\Tests\Notification;

use Atrium\Notification\NotificationAction;
use PHPUnit\Framework\TestCase;

final class NotificationActionTest extends TestCase
{
    public function testLinkActionRoundTrips(): void
    {
        $action = NotificationAction::make('View')
            ->icon('eye')
            ->color('info')
            ->url('https://example.test/articles/1');

        $array = $action->toArray();
        self::assertSame('link', $array['kind']);
        self::assertSame('View', $array['label']);
        self::assertTrue($array['closeOnClick']);
        self::assertArrayHasKey('url', $array);
        self::assertSame('https://example.test/articles/1', $array['url']);

        $restored = NotificationAction::fromArray($array);
        self::assertEquals($array, $restored->toArray());
    }

    public function testEmitActionCarriesEventAndPayload(): void
    {
        $action = NotificationAction::make('Undo')->emit('article:undo', ['id' => 42])->closeOnClick(false);

        $array = $action->toArray();
        self::assertSame('emit', $array['kind']);
        self::assertFalse($array['closeOnClick']);
        self::assertArrayHasKey('event', $array);
        self::assertArrayHasKey('payload', $array);
        self::assertSame('article:undo', $array['event']);
        self::assertSame(['id' => 42], $array['payload']);
    }

    public function testAnActionMustBeEitherLinkOrEmit(): void
    {
        $this->expectException(\LogicException::class);
        NotificationAction::make('Broken')->toArray(); // neither url() nor emit()
    }

    public function testLinkAndEmitAreMutuallyExclusive(): void
    {
        $this->expectException(\LogicException::class);
        NotificationAction::make('Broken')->url('https://x.test')->emit('e');
    }

    public function testDangerousLinkSchemesAreNeutralisedInToArray(): void
    {
        // A link URL is scheme-guarded in PHP (no Twig filter needed downstream).
        // Narrow the discriminated union to link-kind before accessing 'url'.
        $jsArray = NotificationAction::make('x')->url('javascript:alert(1)')->toArray();
        self::assertSame('link', $jsArray['kind']);
        self::assertNull($jsArray['url']);

        $dataArray = NotificationAction::make('x')->url('data:text/html,evil')->toArray();
        self::assertSame('link', $dataArray['kind']);
        self::assertNull($dataArray['url']);

        $relArray = NotificationAction::make('x')->url('/admin/articles/1')->toArray();
        self::assertSame('link', $relArray['kind']);
        self::assertSame('/admin/articles/1', $relArray['url']);

        $mailArray = NotificationAction::make('x')->url('mailto:a@b.test')->toArray();
        self::assertSame('link', $mailArray['kind']);
        self::assertSame('mailto:a@b.test', $mailArray['url']);
    }
}
