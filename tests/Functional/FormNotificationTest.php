<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * A successful Form save raises a real success toast: on the stay path (no
 * redirect) it emits `atrium:notification` on the live channel.
 */
final class FormNotificationTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testSuccessfulSaveEmitsLiveNotificationWhenStaying(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'tag']);

        $component
            ->set('formData', ['name' => 'Cherry', 'slug' => 'cherry', 'active' => true, 'kind' => '', 'region' => ''])
            ->call('save');

        self::assertComponentEmitEvent($component, 'atrium:notification');
    }
}
