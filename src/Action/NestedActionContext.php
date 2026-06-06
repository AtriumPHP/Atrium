<?php

declare(strict_types=1);

namespace Atrium\Action;

/**
 * An {@see ActionContext} for a nested resource (REL-16): it prepends the
 * `{parentResource}/{parentId}` segment to the resource URL, so every per-record
 * URL an {@see Action} builds (`recordUrl`, `recordRootUrl`) and every `rowUrl`
 * becomes the 5-segment nested form — `/admin/{parent}/{pid}/{resource}/{id}/...`
 * — without any action needing to know it is nested.
 */
final readonly class NestedActionContext extends ActionContext
{
    public function __construct(
        string $pathPrefix,
        public string $parentSlug,
        public string $parentId,
        string $slug,
        string $recordId,
    ) {
        parent::__construct($pathPrefix, $slug, $recordId);
    }

    public function resourceUrl(): string
    {
        return rtrim($this->pathPrefix, '/').'/'.$this->parentSlug.'/'.rawurlencode($this->parentId).'/'.$this->slug;
    }
}
