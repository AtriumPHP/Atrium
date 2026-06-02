<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Resource\HookedTagResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The form save path runs the resource's record-lifecycle hooks (mutate +
 * before/after save) and re-checks authorization server-side.
 */
final class FormHooksTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        HookedTagResource::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        HookedTagResource::reset();
        restore_exception_handler();
    }

    public function testCreateRunsMutateAndBeforeAfterSave(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'hooked-tag']);
        $component->set('formData', ['name' => 'Hello World', 'slug' => '']);
        $component->call('save');

        // mutateFormDataBeforeSave derived the slug; the hooks saw the entity.
        self::assertSame(['create:Hello World'], HookedTagResource::$beforeSaved);
        self::assertSame(['create:hello-world'], HookedTagResource::$afterSaved);

        $created = $this->writer()->records[Tag::class] ?? [];
        self::assertCount(1, $created);
        self::assertInstanceOf(Tag::class, $created[0]);
        self::assertSame('hello-world', $created[0]->slug);
    }

    public function testCreateGoesThroughTheResourcePersistenceHook(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'hooked-tag']);
        $component->set('formData', ['name' => 'Hello World', 'slug' => '']);
        $component->call('save');

        // The resource's handleRecordCreation hook ran (an app can route the
        // write through its own service), and the record was still persisted.
        self::assertSame(['hello-world'], HookedTagResource::$created);
        self::assertCount(1, $this->writer()->records[Tag::class] ?? []);
    }

    public function testSaveIsRefusedWhenCreationIsNotAuthorized(): void
    {
        HookedTagResource::$allowCreate = false;

        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'hooked-tag']);
        $component->set('formData', ['name' => 'Nope', 'slug' => '']);
        $component->call('save');

        self::assertSame([], HookedTagResource::$beforeSaved);
        self::assertSame([], $this->writer()->records[Tag::class] ?? []);
    }

    public function testEditingAVanishedRecordWritesNothing(): void
    {
        // entityId that no longer resolves must not create a blank entity.
        $component = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'hooked-tag',
            'entityId' => '999999',
        ]);
        $component->set('formData', ['name' => 'Ghost', 'slug' => '']);
        $component->call('save');

        self::assertSame([], HookedTagResource::$beforeSaved);
        self::assertSame([], $this->writer()->records[Tag::class] ?? []);
    }

    public function testEditFormDoesNotExposeAnUneditableRecord(): void
    {
        HookedTagResource::$allowEdit = false;

        $component = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'hooked-tag',
            'entityId' => '3',
        ]);

        // The record's data must not be hydrated into the form.
        $instance = $component->component();
        self::assertInstanceOf(\Atrium\Twig\Components\Form::class, $instance);
        self::assertSame([], $instance->formData);
    }

    private function writer(): ArrayDataWriter
    {
        $writer = self::getContainer()->get(ArrayDataWriter::class);
        self::assertInstanceOf(ArrayDataWriter::class, $writer);

        return $writer;
    }
}
