<?php

declare(strict_types=1);

namespace Atrium\Tests\Notification;

use Atrium\Notification\Notification;
use Atrium\Notification\NotificationAction;
use Atrium\Notification\NotificationStatus;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    public function testStatusShortcutSetsDefaultIconAndColor(): void
    {
        $array = Notification::make('id-1')->title('Saved')->success()->toArray();

        self::assertSame('id-1', $array['id']);
        self::assertSame('Saved', $array['title']);
        self::assertSame('success', $array['status']);
        self::assertSame('circle-check', $array['icon']);
        self::assertSame('success', $array['color']);
        self::assertNull($array['body']);
        self::assertSame(Notification::DURATION_DEFAULT, $array['duration']);
        self::assertSame([], $array['actions']);
    }

    public function testIconAndColorOverridesWin(): void
    {
        $array = Notification::make('id-2')->title('Hi')->success()->icon('rocket')->color('primary')->toArray();

        self::assertSame('rocket', $array['icon']);
        self::assertSame('primary', $array['color']);
    }

    public function testPersistentClearsDurationAndDurationClearsPersistent(): void
    {
        self::assertNull(Notification::make('a')->title('x')->persistent()->toArray()['duration']);
        self::assertSame(3000, Notification::make('b')->title('x')->persistent()->duration(3000)->toArray()['duration']);
    }

    public function testAutoGeneratesAnIdWhenNoneGiven(): void
    {
        $id = Notification::make()->title('x')->toArray()['id'];
        self::assertNotSame('', $id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id);
    }

    public function testActionsAreSerialized(): void
    {
        $array = Notification::make('c')->title('x')->actions([
            NotificationAction::make('View')->url('https://x.test'),
            NotificationAction::make('Undo')->emit('undo'),
        ])->toArray();

        self::assertCount(2, $array['actions']);
        self::assertSame('link', $array['actions'][0]['kind']);
        self::assertSame('emit', $array['actions'][1]['kind']);
    }

    public function testRoundTripsThroughFromArray(): void
    {
        $original = Notification::make('d')->title('Saved')->body('All good')->warning()
            ->duration(8000)
            ->actions([NotificationAction::make('View')->url('https://x.test')])
            ->toArray();

        self::assertEquals($original, Notification::fromArray($original)->toArray());
    }

    public function testFromArrayRejectsMalformedInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Notification::fromArray(['id' => 'x']); // missing title/status
    }

    public function testStatusDefaultsToNeutral(): void
    {
        $array = Notification::make('e')->title('x')->toArray();
        self::assertSame(NotificationStatus::Neutral->value, $array['status']);
        self::assertSame('bell', $array['icon']);
    }

    public function testToArrayRequiresATitle(): void
    {
        $this->expectException(\LogicException::class);
        Notification::make('x')->toArray(); // no title()
    }
}
