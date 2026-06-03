<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Action\Action;
use Atrium\Action\ActionGroup;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Resource\AdminResource;
use Atrium\Table\Action\BulkDeleteAction;
use Atrium\Table\Action\DeleteAction;
use Atrium\Table\Action\EditAction;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Record-actions fixture: an Edit link, a confirmed Delete server action and a
 * grouped (dropdown) link, to exercise the table's action rendering and the
 * server-driven confirm/run flow.
 */
final class ActionsTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'actions-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        // Header actions are left at the resource default (a CreateAction on the
        // list, via AdminResource::getHeaderActions()).
        return $table
            ->columns([Column::make('name')->searchable()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                // A confirmable server action hidden for every record — must never
                // be surfaced or run, even by a crafted request.
                Action::make('secret')->requiresConfirmation()->visible(false)
                    ->action(static fn (object $record, $writer): null => null),
                ActionGroup::make([
                    Action::make('duplicate')->label('Duplicate')->icon('document')
                        ->url(static fn (object $record): string => '#duplicate'),
                ])->label('More'),
            ])
            ->bulkActions([
                BulkDeleteAction::make(),
                // A hidden bulk action — must never be surfaced or run, even by a
                // crafted request.
                Action::make('purge')->label('Purge')->visible(false)->requiresConfirmation()
                    ->action(static function (array $records, DataWriterInterface $writer): void {}),
            ]);
    }
}
