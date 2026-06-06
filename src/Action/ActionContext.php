<?php

declare(strict_types=1);

namespace Atrium\Action;

/**
 * The routing context an {@see Action} needs to resolve a per-record URL: the
 * panel path prefix, the resource slug and the record's id. Kept tiny and
 * Doctrine-agnostic so actions never reach into the router or the entity. Not
 * `final`: {@see NestedActionContext} extends it to prepend a parent segment for
 * nested resources (REL-16).
 */
readonly class ActionContext
{
    public function __construct(
        public string $pathPrefix,
        public string $slug,
        public string $recordId,
    ) {
    }

    /**
     * The resource index URL, e.g. `/admin/product`.
     */
    public function resourceUrl(): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$this->slug;
    }

    /**
     * A per-record action URL, e.g. `/admin/product/42/edit`.
     */
    public function recordUrl(string $action): string
    {
        return $this->recordRootUrl().'/'.$action;
    }

    /**
     * The bare per-record URL with no action segment, e.g. `/admin/product/42` —
     * the read-only View screen and the counterpart of {@see recordUrl()}.
     */
    public function recordRootUrl(): string
    {
        return $this->resourceUrl().'/'.rawurlencode($this->recordId);
    }
}
