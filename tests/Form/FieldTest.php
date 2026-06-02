<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\CheckboxField;
use Atrium\Form\Field\DateField;
use Atrium\Form\Field\DateTimeField;
use Atrium\Form\Field\NumberField;
use Atrium\Form\Field\SelectField;
use Atrium\Form\Field\TextareaField;
use Atrium\Form\Field\TextField;
use Atrium\Tests\Fixtures\Form\ColorField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

final class FieldTest extends TestCase
{
    public function testFluentDefaultsAndLabelHumanisation(): void
    {
        $field = TextField::make('firstName')->required()->live()->help('Your name');

        self::assertSame('firstName', $field->getName());
        self::assertSame('First Name', $field->getLabel());
        self::assertTrue($field->isRequired());
        self::assertTrue($field->isLive());
        self::assertSame('Your name', $field->getHelp());
        self::assertSame('text', $field->getType());
    }

    public function testRequiredAddsImplicitNotBlank(): void
    {
        $constraints = TextField::make('name')->required()->getConstraints();

        self::assertInstanceOf(NotBlank::class, $constraints[0]);
    }

    public function testEmailHelperSetsInputTypeAndConstraint(): void
    {
        $field = TextField::make('email')->email();

        self::assertSame('email', $field->getInputType());
        self::assertContainsOnlyInstancesOf(Email::class, array_filter(
            $field->getConstraints(),
            static fn ($c): bool => $c instanceof Email,
        ));
    }

    public function testTextNormalisationTrimsAndEmptyBecomesNull(): void
    {
        $field = TextField::make('name');

        self::assertSame('Acme', $field->normalize('  Acme  '));
        self::assertNull($field->normalize('   '));
        self::assertNull($field->normalize(null));
    }

    public function testNumberFieldIntegerAndFloat(): void
    {
        self::assertSame(42, NumberField::make('qty')->integer()->normalize('42'));
        self::assertSame(3.5, NumberField::make('weight')->normalize('3.5'));
        self::assertNull(NumberField::make('qty')->normalize(''));
        self::assertNull(NumberField::make('qty')->normalize('abc'));
    }

    public function testCheckboxNormalisationAndRequiredIsTrue(): void
    {
        $field = CheckboxField::make('active')->required();

        self::assertTrue($field->normalize('1'));
        self::assertTrue($field->normalize('on'));
        self::assertFalse($field->normalize(null));
        self::assertFalse($field->normalize('0'));
        self::assertInstanceOf(IsTrue::class, $field->getConstraints()[0]);
    }

    public function testSelectStaticAndDynamicOptions(): void
    {
        $static = SelectField::make('tier')->options(['a' => 'A', 'b' => 'B']);
        self::assertSame(['a' => 'A', 'b' => 'B'], $static->getOptions());

        $dynamic = SelectField::make('city')->optionsUsing(
            static fn (array $data): array => 'fr' === ($data['country'] ?? null) ? ['paris' => 'Paris'] : ['nyc' => 'New York'],
        );
        self::assertSame(['paris' => 'Paris'], $dynamic->getOptions(['country' => 'fr']));
        self::assertSame(['nyc' => 'New York'], $dynamic->getOptions(['country' => 'us']));
    }

    public function testTextareaRows(): void
    {
        self::assertSame(6, TextareaField::make('bio')->rows(6)->getRows());
        self::assertSame('textarea', TextareaField::make('bio')->getType());
    }

    public function testDateRoundTrip(): void
    {
        $field = DateField::make('dob');
        $model = $field->normalize('2026-03-14');

        self::assertInstanceOf(\DateTimeImmutable::class, $model);
        self::assertSame('2026-03-14', $model->format('Y-m-d'));
        self::assertSame('2026-03-14', $field->toFormValue($model));
        self::assertNull($field->normalize(''));
    }

    public function testBuiltInTemplatesFollowTheConvention(): void
    {
        self::assertSame('@Atrium/components/form/widget/text.html.twig', TextField::make('x')->getTemplate());
        self::assertSame('@Atrium/components/form/widget/select.html.twig', SelectField::make('x')->getTemplate());
        self::assertSame('@Atrium/components/form/widget/checkbox.html.twig', CheckboxField::make('x')->getTemplate());
    }

    public function testCustomFieldShipsItsOwnTemplate(): void
    {
        $field = ColorField::make('brandColor');

        self::assertSame('color', $field->getType());
        self::assertSame('@Acme/fields/color.html.twig', $field->getTemplate());
    }

    public function testOnlyCheckboxRendersItsOwnLabel(): void
    {
        self::assertTrue(CheckboxField::make('active')->rendersOwnLabel());
        self::assertFalse(TextField::make('name')->rendersOwnLabel());
        self::assertFalse(SelectField::make('tier')->rendersOwnLabel());
    }

    public function testDateTimeFieldFormat(): void
    {
        $field = DateTimeField::make('startsAt');
        $model = $field->normalize('2026-03-14T09:30');

        self::assertSame('datetime', $field->getType());
        self::assertInstanceOf(\DateTimeImmutable::class, $model);
        self::assertSame('2026-03-14 09:30', $model->format('Y-m-d H:i'));
        self::assertSame('2026-03-14T09:30', $field->toFormValue($model));
    }
}
