<?php

declare(strict_types=1);

namespace Atrium\Relation;

/**
 * The kind of relationship a {@see Relation} describes. The kind selects the
 * action taxonomy (associate/dissociate vs attach/detach) and the data-layer
 * path (foreign-key scope vs pivot join).
 */
enum RelationKind: string
{
    case OneToMany = 'one_to_many';
    case ManyToMany = 'many_to_many';

    public function usesPivot(): bool
    {
        return self::ManyToMany === $this;
    }
}
