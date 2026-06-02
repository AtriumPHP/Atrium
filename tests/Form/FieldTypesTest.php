<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\ColorField;
use Atrium\Form\Field\HiddenField;
use Atrium\Form\Field\RadioField;
use Atrium\Form\Field\TextField;
use Atrium\Form\Field\ToggleButtonsField;
use Atrium\Form\Field\ToggleField;
use Atrium\Form\Schema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\IsTrue;

final class FieldTypesTest extends TestCase
{
    public function testRadioReusesSelectOptions(): void
    {
        $field = RadioField::make('size')->options(['s' => 'Small', 'l' => 'Large']);

        self::assertSame('radio', $field->getType());
        self::assertSame('@Atrium/components/form/widget/radio.html.twig', $field->getWidgetTemplate());
        self::assertSame(['s' => 'Small', 'l' => 'Large'], $field->getOptions());
    }

    public function testToggleButtonsReuseSelectOptions(): void
    {
        $field = ToggleButtonsField::make('priority')->options(['lo' => 'Low', 'hi' => 'High']);

        self::assertSame('toggle_buttons', $field->getType());
        self::assertSame(['lo' => 'Low', 'hi' => 'High'], $field->getOptions());
    }

    public function testToggleIsABooleanCheckbox(): void
    {
        $field = ToggleField::make('active');

        self::assertSame('toggle', $field->getType());
        self::assertTrue($field->rendersOwnLabel());
        self::assertTrue($field->normalize('1'));
        self::assertFalse($field->normalize(null));

        $required = ToggleField::make('agree')->required()->getConstraints();
        self::assertInstanceOf(IsTrue::class, $required[0]);
    }

    public function testColorNormalisation(): void
    {
        $field = ColorField::make('accent');

        self::assertSame('color', $field->getType());
        self::assertSame('#ff8800', $field->normalize('#ff8800'));
        self::assertNull($field->normalize(''));
        self::assertSame('#000000', $field->toFormValue(null));
        self::assertSame('#abcabc', $field->toFormValue('#abcabc'));
    }

    public function testHiddenStaysInStateButNotInLayout(): void
    {
        $hidden = HiddenField::make('source')->default('web');

        self::assertSame('hidden', $hidden->getType());
        self::assertFalse($hidden->rendersInLayout());
        self::assertTrue(TextField::make('name')->rendersInLayout());

        // It is still a real field — flattened for hydration/persistence.
        $schema = (new Schema())->components([$hidden, TextField::make('name')]);
        self::assertSame(
            ['source', 'name'],
            array_map(static fn ($f): string => $f->getName(), $schema->getFields()),
        );
    }
}
