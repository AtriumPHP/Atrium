<?php

declare(strict_types=1);

namespace Atrium\Twig\Components\Concern;

use Atrium\Notification\Notification;

/**
 * Gives a Live Component a one-liner to raise a toast on the live channel (no
 * reload): emits the `atrium:notification` event the {@see \Atrium\Twig\Components\Notifications}
 * host listens for. The using component must also `use ComponentToolsTrait;`
 * (this trait calls its emit()).
 *
 * @method void emit(string $name, array<string, mixed> $data = [])
 */
trait InteractsWithNotifications
{
    protected function notify(Notification $notification): void
    {
        $this->emit('atrium:notification', ['notification' => $notification->toArray()]);
    }

    protected function notifySuccess(string $title, ?string $body = null): void
    {
        $this->notify(Notification::make()->title($title)->body($body)->success());
    }

    protected function notifyDanger(string $title, ?string $body = null): void
    {
        $this->notify(Notification::make()->title($title)->body($body)->danger());
    }
}
