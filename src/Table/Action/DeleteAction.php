<?php

declare(strict_types=1);

namespace Atrium\Table\Action;

use Atrium\Action\Action;
use Atrium\DataProvider\DataWriterInterface;

/**
 * The built-in "delete" record action: a confirmed server action that removes
 * the record through the {@see DataWriterInterface}. A table-bound specialisation
 * of the generic {@see Action}. The confirmation is server-driven (a re-rendered
 * prompt), so it needs no client JavaScript.
 *
 * The handler is invoked by the table host with `(object $record,
 * DataWriterInterface $writer)`.
 */
final class DeleteAction extends Action
{
    public static function make(string $name = 'delete'): static
    {
        return parent::make($name)
            ->label('Delete')
            ->icon('trash')
            ->color('red')
            ->authorize('delete')
            ->requiresConfirmation()
            ->successNotification('Deleted')
            ->action(static function (object $record, DataWriterInterface $writer): void {
                $writer->delete($record);
            });
    }
}
