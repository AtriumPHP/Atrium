<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Entity\Comment;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The embedded Form mode (REL-M2): hosted inside a relation manager's modal, it
 * applies a preset foreign key on create and emits instead of navigating.
 */
final class FormEmbeddedTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testEmbeddedCreateAppliesPresetForeignKey(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'comment',
            'embedded' => true,
            'presetValues' => ['postId' => '1'],
            'notifyEvent' => 'relation:saved',
        ]);

        $component->set('formData', ['body' => 'A new comment']);
        $component->call('save');

        $created = array_values(array_filter(
            $this->writer()->created,
            static fn (object $e): bool => $e instanceof Comment,
        ));
        self::assertCount(1, $created);
        self::assertInstanceOf(Comment::class, $created[0]);
        self::assertSame('A new comment', $created[0]->body);
        self::assertSame(1, $created[0]->postId);
    }

    private function writer(): ArrayDataWriter
    {
        $writer = self::getContainer()->get(ArrayDataWriter::class);
        \assert($writer instanceof ArrayDataWriter);

        return $writer;
    }
}
