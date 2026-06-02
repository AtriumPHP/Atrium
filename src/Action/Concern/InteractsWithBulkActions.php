<?php

declare(strict_types=1);

namespace Atrium\Action\Concern;

use Atrium\Action\Action;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Adds row selection and confirmable bulk actions to a Live Component.
 *
 * Selection is server-driven and survives re-renders. It has two modes: a list
 * of explicitly {@see $selected} ids, and a "select all matching the query"
 * mode ({@see $selectAll} plus an {@see $excluded} list) so a bulk action can
 * target every record across all pages — not just the visible ones. The host
 * supplies the page's ids, the matching total and how to run an action against
 * the resolved selection.
 *
 * Pairs with {@see InteractsWithActions}: that concern owns per-record actions,
 * this one owns selection and bulk actions. The two confirm flows are
 * independent so a record prompt and a bulk prompt never collide.
 */
trait InteractsWithBulkActions
{
    /**
     * Explicitly selected record ids (used when not in select-all mode).
     *
     * The selection props are intentionally **not** writable: they are mutated
     * only through the LiveActions below (the checkboxes call `toggleRecord` /
     * `togglePage`, never a `data-model` binding), so they stay HMAC-checksummed
     * and a crafted request cannot forge a selection — e.g. set `selectAll` to
     * mass-delete without going through the UI's server-validated steps.
     *
     * @var list<string>
     */
    #[LiveProp]
    public array $selected = [];

    /**
     * "All records matching the current query" mode: every matching record
     * counts as selected except those in {@see $excluded}. Set only by
     * {@see selectAllMatching()} / {@see clearSelection()} (non-writable).
     */
    #[LiveProp]
    public bool $selectAll = false;

    /**
     * Ids explicitly de-selected while in {@see $selectAll} mode. Mutated only by
     * {@see toggleRecord()} / {@see togglePage()} (non-writable).
     *
     * @var list<string>
     */
    #[LiveProp]
    public array $excluded = [];

    /**
     * The bulk action awaiting confirmation, or null when no prompt is open.
     */
    #[LiveProp]
    public ?string $confirmingBulkAction = null;

    /**
     * Add or remove a single record from the selection.
     */
    #[LiveAction]
    public function toggleRecord(#[LiveArg] string $id): void
    {
        if ($this->selectAll) {
            $this->excluded = $this->toggleId($this->excluded, $id);

            return;
        }

        $this->selected = $this->toggleId($this->selected, $id);
    }

    /**
     * Toggle every record on the current page: select them all, or clear the
     * page when it is already fully selected.
     */
    #[LiveAction]
    public function togglePage(): void
    {
        $ids = $this->currentPageIds();
        $fully = $this->isPageFullySelected();

        if ($this->selectAll) {
            $this->excluded = $fully
                ? array_values(array_unique([...$this->excluded, ...$ids]))
                : array_values(array_filter($this->excluded, static fn (string $id): bool => !\in_array($id, $ids, true)));

            return;
        }

        $this->selected = $fully
            ? array_values(array_filter($this->selected, static fn (string $id): bool => !\in_array($id, $ids, true)))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    /**
     * Switch to "all records matching the query" selection.
     */
    #[LiveAction]
    public function selectAllMatching(): void
    {
        $this->selectAll = true;
        $this->selected = [];
        $this->excluded = [];
    }

    /**
     * Clear the entire selection.
     */
    #[LiveAction]
    public function clearSelection(): void
    {
        $this->selectAll = false;
        $this->selected = [];
        $this->excluded = [];
    }

    /**
     * Trigger a bulk action: those needing confirmation open the prompt; the
     * rest run immediately against the current selection.
     */
    #[LiveAction]
    public function requestBulkAction(#[LiveArg] string $name): void
    {
        $action = $this->findBulkAction($name);
        if (null === $action || !$this->canRunBulkAction($action)) {
            return; // unknown / link-only / hidden / nothing selected
        }

        if ($action->needsConfirmation()) {
            $this->confirmingBulkAction = $name;

            return;
        }

        $this->runBulkAction($action);
    }

    /**
     * Run the bulk action the prompt is waiting on, then dismiss it.
     */
    #[LiveAction]
    public function confirmBulkAction(): void
    {
        $action = null === $this->confirmingBulkAction ? null : $this->findBulkAction($this->confirmingBulkAction);
        if (null !== $action && $this->canRunBulkAction($action)) {
            $this->runBulkAction($action);
        }

        $this->cancelBulkAction();
    }

    /**
     * Dismiss the bulk confirmation prompt without acting.
     */
    #[LiveAction]
    public function cancelBulkAction(): void
    {
        $this->confirmingBulkAction = null;
    }

    public function isRecordSelected(string $id): bool
    {
        return $this->selectAll
            ? !\in_array($id, $this->excluded, true)
            : \in_array($id, $this->selected, true);
    }

    public function hasSelection(): bool
    {
        return $this->getSelectedCount() > 0;
    }

    public function getSelectedCount(): int
    {
        return $this->selectAll
            ? max(0, $this->totalSelectableCount() - \count($this->excluded))
            : \count($this->selected);
    }

    public function isSelectAllActive(): bool
    {
        return $this->selectAll;
    }

    public function isPageFullySelected(): bool
    {
        $ids = $this->currentPageIds();
        if ([] === $ids) {
            return false;
        }

        foreach ($ids as $id) {
            if (!$this->isRecordSelected($id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether to offer "select all N matching": the page is fully selected, but
     * the selection does not yet cover every matching record.
     */
    public function canSelectAllMatching(): bool
    {
        return !$this->selectAll
            && $this->isPageFullySelected()
            && $this->getSelectedCount() < $this->totalSelectableCount();
    }

    public function isConfirmingBulk(): bool
    {
        return null !== $this->confirmingBulkAction;
    }

    public function getConfirmingBulkLabel(): string
    {
        return $this->confirmingBulkInstance()?->getLabel() ?? 'Confirm';
    }

    public function getConfirmingBulkColor(): string
    {
        return $this->confirmingBulkInstance()?->getColor() ?? 'primary';
    }

    public function getConfirmingBulkMessage(): string
    {
        $action = $this->confirmingBulkInstance();
        if (null === $action) {
            return '';
        }

        $custom = $action->getCustomConfirmationMessage();
        if (null !== $custom) {
            return $custom;
        }

        // The action's label is already the prompt's title, so keep the body
        // label-agnostic — interpolating the verb reads badly when the label
        // itself mentions the selection (e.g. "Delete selected").
        $count = $this->getSelectedCount();

        return \sprintf(
            'This will affect %d selected record%s. Do you want to continue?',
            $count,
            1 === $count ? '' : 's',
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function toggleId(array $ids, string $id): array
    {
        if (\in_array($id, $ids, true)) {
            return array_values(array_filter($ids, static fn (string $candidate): bool => $candidate !== $id));
        }

        $ids[] = $id;

        return $ids;
    }

    private function confirmingBulkInstance(): ?Action
    {
        return null === $this->confirmingBulkAction ? null : $this->findBulkAction($this->confirmingBulkAction);
    }

    /**
     * Whether the action may run: it is a server action, visible, and there is a
     * selection. Checked before opening a prompt and again before running, so a
     * crafted request cannot surface or run a hidden action.
     */
    private function canRunBulkAction(Action $action): bool
    {
        return $action->isServerAction() && $action->isVisible() && $this->hasSelection();
    }

    /**
     * Find a bulk action by name.
     */
    abstract protected function findBulkAction(string $name): ?Action;

    /**
     * Run a bulk action against the current selection, then clear it.
     */
    abstract protected function runBulkAction(Action $action): void;

    /**
     * The ids of the records on the current page.
     *
     * @return list<string>
     */
    abstract protected function currentPageIds(): array;

    /**
     * Total number of records matching the current query (across all pages).
     */
    abstract protected function totalSelectableCount(): int;
}
