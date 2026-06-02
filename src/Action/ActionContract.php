<?php

declare(strict_types=1);

namespace Atrium\Action;

/**
 * The shared contract for anything placed in an actions list — a single
 * {@see Action} or an {@see ActionGroup}. A host (a table, later a form or page)
 * renders and runs them uniformly through this contract, with no type switches:
 * resolve each to a view descriptor, and flatten to its runnable leaf actions.
 */
interface ActionContract
{
    /**
     * Whether to show this for the given subject (a record, etc.).
     */
    public function isVisibleFor(object $subject): bool;

    /**
     * The Twig template that renders this entry. Override in a custom action or
     * group to ship your own renderer from any bundle — the host includes
     * whatever each entry declares, so no central markup needs changing.
     */
    public function getTemplate(): string;

    /**
     * Resolve to a render-ready descriptor for the subject (a `kind: 'action'`
     * trigger, or a `kind: 'group'` dropdown of trigger descriptors). The
     * descriptor carries its `template` so the host can render it generically.
     *
     * The optional `$authorize` callback decides whether a leaf {@see Action} is
     * permitted for the subject; a group uses it to drop unauthorised children.
     *
     * @param (\Closure(Action): bool)|null $authorize
     *
     * @return array<string, mixed>
     */
    public function toView(object $subject, ActionContext $context, ?string $id, ?\Closure $authorize = null): array;

    /**
     * The runnable leaf actions behind this entry — itself for an {@see Action},
     * its children for an {@see ActionGroup}. Used to resolve a server action by
     * name when one is triggered.
     *
     * @return list<Action>
     */
    public function flatten(): array;
}
