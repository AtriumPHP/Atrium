<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Table\Action\DeleteAction;
use Atrium\Table\Action\EditAction;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture exercising the authorization and record-lifecycle hooks.
 *
 * The flags and recorders are **static** on purpose: the Live Component test
 * harness reboots the kernel between interactions, so a per-instance flag set
 * before a call would be lost. Process-level static state survives the reboot —
 * a test toggles the flags and reads the recorders directly. Call {@see reset()}
 * in the test's setUp so state never leaks between tests.
 */
final class HookedTagResource extends AdminResource
{
    public static bool $allowCreate = true;

    public static bool $allowEdit = true;

    public static bool $allowDelete = true;

    /** @var list<string> */
    public static array $beforeSaved = [];

    /** @var list<string> */
    public static array $afterSaved = [];

    /** @var list<string> */
    public static array $deleted = [];

    public static function reset(): void
    {
        self::$allowCreate = true;
        self::$allowEdit = true;
        self::$allowDelete = true;
        self::$beforeSaved = [];
        self::$afterSaved = [];
        self::$deleted = [];
    }

    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'hooked-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table
            ->columns([Column::make('name')->searchable()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([
            TextField::make('name')->required(),
            TextField::make('slug'),
        ]);
    }

    public function canCreate(): bool
    {
        return self::$allowCreate;
    }

    public function canEdit(object $record): bool
    {
        return self::$allowEdit;
    }

    public function canDelete(object $record): bool
    {
        return self::$allowDelete;
    }

    public function mutateFormDataBeforeSave(array $data, string $operation): array
    {
        $name = $data['name'] ?? '';
        $data['slug'] = \is_string($name) ? strtolower(str_replace(' ', '-', $name)) : '';

        return $data;
    }

    public function beforeSave(object $record, string $operation): void
    {
        if ($record instanceof Tag) {
            self::$beforeSaved[] = $operation.':'.$record->name;
        }
    }

    public function afterSave(object $record, string $operation): void
    {
        if ($record instanceof Tag) {
            self::$afterSaved[] = $operation.':'.$record->slug;
        }
    }

    public function afterDelete(object $record): void
    {
        if ($record instanceof Tag) {
            self::$deleted[] = $record->slug;
        }
    }
}
