<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Twig\Components\Form;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class FormComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testValidationBlocksSaveWhenRequiredFieldMissing(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'tag']);

        $component->call('save');

        $form = $this->form($component);
        self::assertArrayHasKey('name', $form->errors);
        self::assertFalse($form->saved);
    }

    public function testSuccessfulCreatePersistsThroughTheWriter(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'tag']);

        $component
            ->set('formData', ['name' => 'Cherry', 'slug' => 'cherry', 'active' => true, 'kind' => '', 'region' => ''])
            ->call('save');

        self::assertTrue($this->form($component)->saved);

        $writer = self::getContainer()->get(ArrayDataWriter::class);
        self::assertInstanceOf(ArrayDataWriter::class, $writer);
        $tags = $writer->records[Tag::class] ?? [];
        self::assertCount(1, $tags);
        self::assertInstanceOf(Tag::class, $tags[0]);
        self::assertSame('Cherry', $tags[0]->name);
        self::assertTrue($tags[0]->active);
    }

    public function testSuccessfulSaveRedirectsWhenPageSuppliesUrl(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', [
            'resource' => 'tag',
            'redirectAfterSave' => '/admin/tag',
        ]);

        $component
            ->set('formData', ['name' => 'Plum', 'slug' => 'plum', 'active' => false, 'kind' => '', 'region' => ''])
            ->call('save');

        self::assertTrue($component->response()->isRedirect('/admin/tag'));
    }

    public function testDependentSelectOptionsReactToParentField(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'tag']);

        $fruit = $component->set('formData', ['name' => 'x', 'slug' => '', 'active' => false, 'kind' => 'fruit', 'region' => ''])->render()->toString();
        self::assertStringContainsString('Europe', $fruit);
        self::assertStringNotContainsString('China', $fruit);

        $tool = $component->set('formData', ['name' => 'x', 'slug' => '', 'active' => false, 'kind' => 'tool', 'region' => ''])->render()->toString();
        self::assertStringContainsString('China', $tool);
        self::assertStringNotContainsString('Europe', $tool);
    }

    public function testEditPrefillsFormDataFromEntity(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'tag', 'entityId' => '3']);

        $form = $this->form($component);
        self::assertTrue($form->isEdit());
        self::assertSame('Tag 03', $form->formData['name']);
        self::assertSame('tag-03', $form->formData['slug']);
    }

    private function form(TestLiveComponent $component): Form
    {
        $instance = $component->component();
        self::assertInstanceOf(Form::class, $instance);

        return $instance;
    }
}
