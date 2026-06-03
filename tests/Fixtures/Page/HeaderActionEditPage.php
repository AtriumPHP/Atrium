<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Page;

use Atrium\Action\Action;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Page\EditPage;
use Atrium\Page\PageContext;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Edit page exercising page header actions hosted by the Form component: a
 * non-destructive "rename" (the record stays in scope, so the form re-renders)
 * and a destructive "archive" (the record falls out of scope, so the form
 * redirects to the list).
 */
final class HeaderActionEditPage extends EditPage
{
    public function getHeaderActions(PageContext $context): array
    {
        return [
            Action::make('rename')->label('Append bang')
                ->action(static function (object $record, DataWriterInterface $writer): void {
                    if ($record instanceof Tag) {
                        $record->name .= '!';
                    }
                    $writer->update($record);
                }),
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
