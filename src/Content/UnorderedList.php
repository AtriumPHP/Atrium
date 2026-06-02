<?php

declare(strict_types=1);

namespace Atrium\Content;

/**
 * A bulleted list of plain-text items — handy for instructions or requirement
 * checklists shown alongside a form.
 */
class UnorderedList extends ContentComponent
{
    /**
     * @param list<string|\Stringable> $items
     */
    public function __construct(
        private readonly array $items,
    ) {
    }

    /**
     * @param list<string|\Stringable> $items
     */
    public static function make(array $items): self
    {
        return new self($items);
    }

    /**
     * @return list<string>
     */
    public function getItems(): array
    {
        return array_map(static fn (string|\Stringable $item): string => (string) $item, $this->items);
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/content/unordered_list.html.twig';
    }
}
