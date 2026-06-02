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

    private function base(): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$this->resourceSlug;
    }
}
