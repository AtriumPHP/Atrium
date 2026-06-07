<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Twig;

use Atrium\Twig\Components\Concern\InteractsWithNotifications;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * A throwaway component that exercises {@see InteractsWithNotifications}: it raises
 * a toast on the live channel, proving the trait's emit() reaches the
 * {@see \Atrium\Twig\Components\Notifications} host (which listens for the same
 * `atrium:notification` event) from a *different* component.
 */
#[AsLiveComponent(name: 'Atrium:Test:Notifying', template: '@AtriumTest/notifying.html.twig')]
final class NotifyingComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;
    use InteractsWithNotifications;

    #[LiveAction]
    public function raiseSuccess(): void
    {
        $this->notifySuccess('Persisted', 'It worked.');
    }
}
