<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\TextField;
use Atrium\Form\Get;
use Atrium\Form\Set;
use PHPUnit\Framework\TestCase;

final class FieldPresentationTest extends TestCase
{
    public function testPresentationDefaults(): void
    {
        $field = TextField::make('x');

        self::assertNull($field->getPlaceholder());
        self::assertFalse($field->isAutofocused());
        self::assertFalse($field->hasHiddenLabel());
    }

    public function testPresentationSetters(): void
    {
        $field = TextField::make('email')
            ->placeholder('you@example.com')
            ->autofocus()
            ->hiddenLabel();

        self::assertSame('you@example.com', $field->getPlaceholder());
        self::assertTrue($field->isAutofocused());
        self::assertTrue($field->hasHiddenLabel());
    }

    public function testAfterStateUpdatedImpliesLiveAndRuns(): void
    {
        $captured = null;
        $field = TextField::make('name')->afterStateUpdated(
            static function (mixed $state, Get $get, Set $set) use (&$captured): void {
                $captured = $state;
            },
        );

        self::assertTrue($field->isLive(), 'afterStateUpdated should imply live()');
        self::assertTrue($field->hasAfterStateUpdated());

        $field->runAfterStateUpdated('Hello', new Get([]), new Set(static fn (string $k, mixed $v) => null));
        self::assertSame('Hello', $captured);
    }
}
