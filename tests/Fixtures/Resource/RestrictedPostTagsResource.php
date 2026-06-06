<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

/**
 * A Post↔Tag parent that forbids both attaching and detaching — proves the
 * relation manager hides and refuses the Attach/Detach affordances when the
 * parent denies them (REL-12).
 */
final class RestrictedPostTagsResource extends PostTagsResource
{
    public function getSlug(): string
    {
        return 'post-tags-restricted';
    }

    public function canAttach(object $parent, object $child): bool
    {
        return false;
    }

    public function canDetach(object $parent, object $child): bool
    {
        return false;
    }
}
