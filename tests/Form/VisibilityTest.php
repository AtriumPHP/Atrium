<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\TextField;
use Atrium\Form\Get;
use PHPUnit\Framework\TestCase;

final class VisibilityTest extends TestCase
{
    public function testGetReadsFormState(): void
    {
        $get = new Get(['country' => 'us', 'tier' => null]);

        self::assertSame('us', $get('country'));
        self::assertNull($get('tier'));
        self::assertNull($get('missing'));
    }

    public function testVisibleByDefault(): void
    {
        self::assertTrue(TextField::make('x')->isVisible(new Get([]), 'create'));
    }

    public function testBooleanVisibility(): void
    {
        self::assertFalse(TextField::make('x')->visible(false)->isVisible(new Get([]), 'create'));
        self::assertFalse(TextField::make('x')->hidden()->isVisible(new Get([]), 'create'));
        self::assertTrue(TextField::make('x')->hidden(false)->isVisible(new Get([]), 'create'));
    }

    public function testClosureVisibilityReactsToOtherField(): void
    {
        $field = TextField::make('state')->visible(static fn (Get $get): bool => 'us' === $get('country'));

        self::assertTrue($field->isVisible(new Get(['country' => 'us']), 'create'));
        self::assertFalse($field->isVisible(new Get(['country' => 'fr']), 'create'));
    }

    public function testHiddenClosureIsInverted(): void
    {
        $field = TextField::make('legacy')->hidden(static fn (Get $get): bool => 'us' === $get('country'));

        self::assertFalse($field->isVisible(new Get(['country' => 'us']), 'create'));
        self::assertTrue($field->isVisible(new Get(['country' => 'fr']), 'create'));
    }

    public function testOperationGates(): void
    {
        $editOnly = TextField::make('vat')->visibleOn('edit');
        self::assertFalse($editOnly->isVisible(new Get([]), 'create'));
        self::assertTrue($editOnly->isVisible(new Get([]), 'edit'));

        $notOnEdit = TextField::make('captcha')->hiddenOn('edit');
        self::assertTrue($notOnEdit->isVisible(new Get([]), 'create'));
        self::assertFalse($notOnEdit->isVisible(new Get([]), 'edit'));
    }
}
