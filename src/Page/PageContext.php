<?php

declare(strict_types=1);

namespace Atrium\Page;

/**
 * The routing/URL context handed to a {@see Page} so its hooks can build links
 * without knowing the panel's path prefix.
 */
final readonly class PageContext
{
    /**
     * @param list<object> $parentRecords resolved ancestry, nearest last (empty when not nested)
     */
    public function __construct(
        public string $resourceSlug,
        public string $pathPrefix,
        public ?string $entityId = null,
        public string $singularLabel = '',
        public string $pluralLabel = '',
        public ?string $parentResourceSlug = null,
        public ?string $parentRecordId = null,
        public array $parentRecords = [],
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

    /**
     * A nested URL for this resource under its parent segment (REL-16). `index` and
     * `create` take no id; `edit`/`view` take the record id. Falls back to the flat
     * URL when the context is not nested. The route shape carries a single parent
     * segment, so deeper ancestry is reached by navigating, not by extra segments.
     */
    public function nestedUrl(string $action, ?string $id = null): string
    {
        if (null === $this->parentResourceSlug || null === $this->parentRecordId) {
            return match ($action) {
                'index' => $this->indexUrl(),
                'create' => $this->createUrl(),
                'edit' => $this->editUrl((string) $id),
                default => $this->viewUrl((string) $id),
            };
        }

        $base = rtrim($this->pathPrefix, '/').'/'.$this->parentResourceSlug.'/'.rawurlencode($this->parentRecordId).'/'.$this->resourceSlug;

        return match ($action) {
            'index' => $base,
            'create' => $base.'/new',
            'edit' => $base.'/'.(string) $id.'/edit',
            default => $base.'/'.(string) $id,
        };
    }

    private function base(): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$this->resourceSlug;
    }
}
