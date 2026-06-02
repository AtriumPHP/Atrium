<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\NumberField;
use Atrium\Form\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Regex;

final class ValidationRulesTest extends TestCase
{
    public function testStringLengthHelpers(): void
    {
        $max = TextField::make('x')->maxLength(5)->getConstraints();
        self::assertInstanceOf(Length::class, $max[0]);
        self::assertSame(5, $max[0]->max);

        $min = TextField::make('x')->minLength(3)->getConstraints();
        self::assertInstanceOf(Length::class, $min[0]);
        self::assertSame(3, $min[0]->min);

        $exact = TextField::make('x')->length(4)->getConstraints();
        self::assertInstanceOf(Length::class, $exact[0]);
        self::assertSame(4, $exact[0]->min);
        self::assertSame(4, $exact[0]->max);
    }

    public function testRegexHelper(): void
    {
        $constraints = TextField::make('x')->regex('/^[a-z]+$/')->getConstraints();

        self::assertInstanceOf(Regex::class, $constraints[0]);
        self::assertSame('/^[a-z]+$/', $constraints[0]->pattern);
    }

    public function testNumericBounds(): void
    {
        $constraints = NumberField::make('qty')->min(1)->max(10)->getConstraints();

        self::assertInstanceOf(GreaterThanOrEqual::class, $constraints[0]);
        self::assertSame(1, $constraints[0]->value);
        self::assertInstanceOf(LessThanOrEqual::class, $constraints[1]);
        self::assertSame(10, $constraints[1]->value);
    }

    public function testRequiredStillPrependsNotBlank(): void
    {
        $constraints = TextField::make('x')->required()->maxLength(5)->getConstraints();

        self::assertCount(2, $constraints);
        self::assertInstanceOf(\Symfony\Component\Validator\Constraints\NotBlank::class, $constraints[0]);
        self::assertInstanceOf(Length::class, $constraints[1]);
    }
}
