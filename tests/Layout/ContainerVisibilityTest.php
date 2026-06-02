<?php

declare(strict_types=1);

namespace Atrium\Tests\Layout;

use Atrium\Form\Field\TextField;
use Atrium\Form\Get;
use Atrium\Layout\Section;
use PHPUnit\Framework\TestCase;

/**
 * Visibility is shared between fields and layout containers via the same
 * Atrium\Layout concern — a whole Section can be conditionally shown.
 */
final class ContainerVisibilityTest extends TestCase
{
    public function testSectionVisibleByDefault(): void
    {
        self::assertTrue(Section::make('A')->isVisible(new Get([]), 'create'));
    }

    public function testSectionClosureVisibility(): void
    {
        $section = Section::make('Publishing')
            ->visible(static fn (Get $get): bool => 'published' === $get('status'));

        self::assertTrue($section->isVisible(new Get(['status' => 'published']), 'create'));
        self::assertFalse($section->isVisible(new Get(['status' => 'draft']), 'create'));
    }

    public function testSectionOperationVisibility(): void
    {
        $section = Section::make('Audit')->visibleOn('edit');

        self::assertFalse($section->isVisible(new Get([]), 'create'));
        self::assertTrue($section->isVisible(new Get([]), 'edit'));
    }

    public function testFieldsAndContainersShareTheSameContract(): void
    {
        // A field accepts the very same Get (it implements StateAccessor).
        self::assertTrue(TextField::make('x')->isVisible(new Get([]), 'create'));
    }
}
