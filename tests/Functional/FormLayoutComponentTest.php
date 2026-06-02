<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Twig\Components\Form;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * SCH-* : a form whose schema is a Section + nested Grid renders its layout and
 * still hydrates/validates/persists through the flattened fields.
 */
final class FormLayoutComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testLayoutTreeRendersSectionGridAndSpans(): void
    {
        $html = $this->createLiveComponent('Atrium:Form', ['resource' => 'layout-tag'])
            ->render()
            ->toString();

        // Section chrome + description.
        self::assertStringContainsString('Identity', $html);
        self::assertStringContainsString('Naming and slug.', $html);
        // Responsive grid + the full-width span on "slug".
        self::assertStringContainsString('lg:grid-cols-2', $html);
        self::assertStringContainsString('col-span-full', $html);
        // Flex row + a child that opted out of growing.
        self::assertStringContainsString('md:flex-row', $html);
        self::assertStringContainsString('flex-none', $html);
        // A static Text content node renders (and is not a form input).
        self::assertStringContainsString('All fields marked with an asterisk are required.', $html);
        // The flattened fields still render their inputs.
        self::assertStringContainsString('atrium_name', $html);
        self::assertStringContainsString('atrium_slug', $html);
        self::assertStringContainsString('atrium_kind', $html);
    }

    public function testClosureVisibilityReactsToALiveField(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'layout-tag']);

        // "cultivar" is hidden until kind is "fruit".
        $hidden = $component->render()->toString();
        self::assertStringNotContainsString('atrium_cultivar', $hidden);

        $shown = $component->set('formData', ['kind' => 'fruit'])->render()->toString();
        self::assertStringContainsString('atrium_cultivar', $shown);

        $gone = $component->set('formData', ['kind' => 'tool'])->render()->toString();
        self::assertStringNotContainsString('atrium_cultivar', $gone);
    }

    public function testOperationVisibility(): void
    {
        $create = $this->createLiveComponent('Atrium:Form', ['resource' => 'layout-tag'])
            ->render()->toString();
        self::assertStringNotContainsString('atrium_notes', $create);

        $edit = $this->createLiveComponent('Atrium:Form', ['resource' => 'layout-tag', 'entityId' => '3'])
            ->render()->toString();
        self::assertStringContainsString('atrium_notes', $edit);
    }

    public function testAfterStateUpdatedDerivesSlugFromName(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'layout-tag']);

        $component->set('formData', ['name' => 'Hello World'])->render();

        self::assertSame('hello-world', $this->form($component)->formData['slug'] ?? null);
    }

    public function testLayoutFormStillPersists(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'layout-tag']);

        $component
            ->set('formData', ['name' => 'Gridberry', 'slug' => 'grid', 'active' => true, 'kind' => 'fruit'])
            ->call('save');

        $writer = self::getContainer()->get(ArrayDataWriter::class);
        self::assertInstanceOf(ArrayDataWriter::class, $writer);
        $tags = $writer->records[Tag::class] ?? [];
        self::assertCount(1, $tags);
        self::assertInstanceOf(Tag::class, $tags[0]);
        self::assertSame('Gridberry', $tags[0]->name);
        self::assertTrue($tags[0]->active);
    }

    private function form(TestLiveComponent $component): Form
    {
        $instance = $component->component();
        self::assertInstanceOf(Form::class, $instance);

        return $instance;
    }
}
