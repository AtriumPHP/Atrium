<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Entity\Tag;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

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
        // The flattened fields still render their inputs.
        self::assertStringContainsString('atrium_name', $html);
        self::assertStringContainsString('atrium_slug', $html);
        self::assertStringContainsString('atrium_kind', $html);
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
}
