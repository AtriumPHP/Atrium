<?php

declare(strict_types=1);

namespace Atrium\Table\Action;

use Atrium\Action\Action;
use Atrium\DataProvider\DataWriterInterface;

/**
 * The built-in bulk "delete" action: a confirmed server action that removes
 * every selected record through the {@see DataWriterInterface}. A table-bound
 * specialisation of the generic {@see Action}. The confirmation is server-driven
 * (a re-rendered prompt), so it needs no client JavaScript.
 *
 * The handler is invoked by the table host with the selected records and the
 * writer: `(list<object> $records, DataWriterInterface $writer)`.
 */
final class BulkDeleteAction extends Action
{
    public static function make(string $name = 'delete'): static
    {
        return parent::make($name)
            ->label('Delete selected')
            ->icon('trash')
            ->color('red')
            ->requiresConfirmation()
            ->action(static function (array $records, DataWriterInterface $writer): void {
                foreach ($records as $record) {
                    if (\is_object($record)) {
                        $writer->delete($record);
                    }
                }
            });
    }
}
