<?php

declare(strict_types=1);

namespace Atrium\Page;

/**
 * The routing/URL context handed to a {@see Page} so its hooks can build links
 * without knowing the panel's path prefix.
 */
final readonly class PageContext
{
    public function __construct(
        public string $resourceSlug,
        public string $pathPrefix,
        public ?string $entityId = null,
        public string $singularLabel = '',
        public string $pluralLabel = '',
    ) {
    }

    public function indexUrl(): string
    {
        return $this->base();
    }

    public function createUrl(): string
    {
        return $this->base().'/new';
    }

    public function editUrl(string $id): string
    {
        return $this->base().'/'.$id.'/edit';
    }

    /**
     * The read-only View screen for a record — the bare record URL (no action
     * segment), the counterpart of {@see editUrl()}.
     */
    public function viewUrl(string $id): string
    {
        return $this->base().'/'.$id;
    }

    private function base(): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$this->resourceSlug;
    }
}
