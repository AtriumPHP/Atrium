<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A node in a layout tree.
 *
 * The layout subsystem is deliberately view-agnostic: a {@see Component} is
 * anything that can be placed in a responsive grid and rendered via its own Twig
 * template. Form fields, layout containers (Grid, Section, Fieldset) and — in
 * future — dashboard widgets or infolist entries all implement this contract, so
 * the same grid/section machinery powers forms, dashboards and other views.
 *
 * Rendering is uniform: a container renders its children by including each
 * child's {@see getTemplate()}; a leaf renders its own content. Nothing in the
 * renderer is form-specific.
 *
 * Part of the public API contract — treat changes as BC-relevant.
 */
interface Component
{
    /**
     * Child components, or an empty list for a leaf node.
     *
     * @return list<Component>
     */
    public function getChildComponents(): array;

    /**
     * The Twig template that renders this node inside a layout (for a field this
     * is its wrapper; for a container, the container chrome that recurses).
     */
    public function getTemplate(): string;

    /**
     * Tailwind classes sizing this node within its parent grid, or '' for the
     * default single-column span.
     */
    public function getColumnSpanClass(): string;
}
