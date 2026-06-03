<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Action\Action;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Page\PageContext;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture exercising page header actions on the edit screen, hosted by the Form
 * component. Scoped to active tags, so the "archive" action (which sets
 * active = false) makes the record fall out of scope — the Form then re-resolves
 * it, finds nothing, and redirects to the list.
 */
final class HeaderActionTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'header-action-tag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('name')]);
    }

    public function scopeQuery(DataQuery $query): DataQuery
    {
        return $query->withFilters(['active' => true]);
    }

    public function getHeaderActions(string $action, PageContext $context): array
    {
        if ('edit' !== $action) {
            return [];
        }

        return [
            // Non-destructive: the record stays in scope, so the form re-renders.
            Action::make('rename')->label('Append bang')
                ->action(static function (object $record, DataWriterInterface $writer): void {
                    if ($record instanceof Tag) {
                        $record->name .= '!';
                    }
                    $writer->update($record);
                }),
            // Destructive (out of scope afterwards): the form redirects to the list.
            Action::make('archive')->label('Archive')->requiresConfirmation()
                ->action(static function (object $record, DataWriterInterface $writer): void {
                    if ($record instanceof Tag) {
                        $record->active = false;
                    }
                    $writer->update($record);
                }),
        ];
    }
}
