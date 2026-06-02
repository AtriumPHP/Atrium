<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Action\Action;
use Atrium\DataProvider\DataWriterInterface;
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

    /** @var list<string> */
    public static array $created = [];

    /** @var list<string> */
    public static array $updated = [];

    /** @var list<string> */
    public static array $validated = [];

    /** @var list<string> */
    public static array $actionLog = [];

    /** @var list<string> */
    public static array $bulkLog = [];

    public static bool $bulkRan = false;

    public static function reset(): void
    {
        self::$allowCreate = true;
        self::$allowEdit = true;
        self::$allowDelete = true;
        self::$beforeSaved = [];
        self::$afterSaved = [];
        self::$deleted = [];
        self::$created = [];
        self::$updated = [];
        self::$validated = [];
        self::$actionLog = [];
        self::$bulkLog = [];
        self::$bulkRan = false;
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
            ->recordActions([EditAction::make(), DeleteAction::make()])
            // A bulk action gated by a panel-level ability — must be refused at
            // execution (not merely hidden) when creation is denied.
            ->bulkActions([
                Action::make('touch')->label('Touch')->authorize('create')
                    ->action(static function (array $records, DataWriterInterface $writer): void {
                        self::$bulkRan = true;
                    }),
            ]);
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

    public function mutateFormDataBeforeFill(array $data, string $operation): array
    {
        // Seed a create-form default (proves the fill hook now runs on create).
        if ('create' === $operation && '' === ($data['name'] ?? '')) {
            $data['name'] = 'Seeded';
        }

        return $data;
    }

    public function mutateFormDataBeforeValidate(array $data, string $operation): array
    {
        // Normalise raw input before validation sees it.
        if (\is_string($data['name'] ?? null)) {
            $data['name'] = trim($data['name']);
        }

        return $data;
    }

    public function afterValidate(array $data, string $operation): void
    {
        $name = $data['name'] ?? '';
        self::$validated[] = $operation.':'.(\is_string($name) ? $name : '');
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

    public function beforeAction(string $action, object $record): void
    {
        self::$actionLog[] = 'before:'.$action;
    }

    public function afterAction(string $action, object $record): void
    {
        self::$actionLog[] = 'after:'.$action;
    }

    public function beforeBulkAction(string $action, array $records): void
    {
        self::$bulkLog[] = 'before:'.$action.':'.\count($records);
    }

    public function afterBulkAction(string $action, array $records): void
    {
        self::$bulkLog[] = 'after:'.$action.':'.\count($records);
    }

    public function handleRecordCreation(object $record, DataWriterInterface $writer): void
    {
        if ($record instanceof Tag) {
            self::$created[] = $record->slug;
        }

        // Still write through the default path, but record that the resource's
        // own persistence hook (not the form's writer call) ran.
        $writer->create($record);
    }

    public function handleRecordUpdate(object $record, DataWriterInterface $writer): void
    {
        if ($record instanceof Tag) {
            self::$updated[] = $record->slug;
        }

        $writer->update($record);
    }
}
