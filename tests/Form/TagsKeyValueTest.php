<?php

declare(strict_types=1);

namespace Atrium\Tests\Form;

use Atrium\Form\Field\KeyValueField;
use Atrium\Form\Field\RepeatableField;
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

    public function testTagsFormValueIsRowList(): void
    {
        // The form state is the ordered row list (empties kept for editing).
        self::assertSame(['php', 'symfony'], TagsField::make('labels')->toFormValue(['php', 'symfony']));
        self::assertSame([], TagsField::make('labels')->toFormValue(null));
        self::assertSame(['a', 'b'], TagsField::make('labels')->toFormValue('a, b'));
    }

    public function testTagsIsRepeatable(): void
    {
        $field = TagsField::make('labels');

        self::assertInstanceOf(RepeatableField::class, $field);
        self::assertSame('', $field->newRow());
        self::assertSame(['php', ''], $field->rows(['php', '']));
    }

    public function testKeyValueNormalisesFromLinesMapAndRows(): void
    {
        $field = KeyValueField::make('meta');

        self::assertSame('key_value', $field->getType());
        self::assertSame(
            ['env' => 'prod', 'region' => 'eu-west'],
            $field->normalize("env: prod\nregion: eu-west\n\nignored-line"),
        );
        self::assertSame(
            ['env' => 'prod'],
            $field->normalize([['key' => 'env', 'value' => 'prod'], ['key' => '', 'value' => 'dropped']]),
        );
    }

    public function testKeyValueFormValueIsRowList(): void
    {
        self::assertSame(
            [['key' => 'a', 'value' => '1'], ['key' => 'b', 'value' => '2']],
            KeyValueField::make('meta')->toFormValue(['a' => '1', 'b' => '2']),
        );
        self::assertSame([], KeyValueField::make('meta')->toFormValue(null));
    }

    public function testKeyValueIsRepeatable(): void
    {
        $field = KeyValueField::make('meta');

        self::assertInstanceOf(RepeatableField::class, $field);
        self::assertSame(['key' => '', 'value' => ''], $field->newRow());
        self::assertSame(
            [['key' => 'env', 'value' => 'prod']],
            $field->rows([['key' => 'env', 'value' => 'prod']]),
        );
    }
}
