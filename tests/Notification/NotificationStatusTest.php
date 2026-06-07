<?php

declare(strict_types=1);

namespace Atrium\Tests\Notification;

use Atrium\Notification\NotificationStatus;
use PHPUnit\Framework\TestCase;

final class NotificationStatusTest extends TestCase
{
    public function testEachStatusHasADefaultIconAndSemanticColor(): void
    {
        self::assertSame('circle-check', NotificationStatus::Success->defaultIcon());
        self::assertSame('success', NotificationStatus::Success->defaultColor());

        self::assertSame('circle-x', NotificationStatus::Danger->defaultIcon());
        self::assertSame('danger', NotificationStatus::Danger->defaultColor());

        self::assertSame('triangle-alert', NotificationStatus::Warning->defaultIcon());
        self::assertSame('warning', NotificationStatus::Warning->defaultColor());

        self::assertSame('info', NotificationStatus::Info->defaultIcon());
        self::assertSame('info', NotificationStatus::Info->defaultColor());

        self::assertSame('bell', NotificationStatus::Neutral->defaultIcon());
        self::assertSame('gray', NotificationStatus::Neutral->defaultColor());
    }
}
