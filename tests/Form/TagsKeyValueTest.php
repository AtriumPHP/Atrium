<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\KeyValueField;
use Atrium\Form\Field\TagsField;
use PHPUnit\Framework\TestCase;

final class TagsKeyValueTest extends TestCase
{
    public function testTagsNormaliseFromCommaString(): void
    {
        $field = TagsField::make('labels');

        self::assertSame('tags', $field->getType());
        self::assertSame(['php', 'symfony', 'ux'], $field->normalize(' php, symfony , ux ,, '));
        self::assertSame(['a', 'b'], $field->normalize(['a', '', 'b']));
    }

    public function testTagsFormatToCommaString(): void
    {
        self::assertSame('php, symfony', TagsField::make('labels')->toFormValue(['php', 'symfony']));
        self::assertSame('', TagsField::make('labels')->toFormValue(null));
    }

    public function testKeyValueNormalisesLines(): void
    {
        $field = KeyValueField::make('meta');

        self::assertSame('key_value', $field->getType());
        self::assertSame(
            ['env' => 'prod', 'region' => 'eu-west'],
            $field->normalize("env: prod\nregion: eu-west\n\nignored-line"),
        );
    }

    public function testKeyValueFormatsToLines(): void
    {
        self::assertSame(
            "a: 1\nb: 2",
            KeyValueField::make('meta')->toFormValue(['a' => '1', 'b' => '2']),
        );
    }
}
