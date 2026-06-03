<?php

declare(strict_types=1);

namespace Atrium\Widget;

/**
 * Base class for every dashboard / embeddable widget (WGT-03).
 *
 * A widget is a *descriptor service*, not a Live Component: you write a subclass,
 * inject whatever produces its data, and return already-computed values. The
 * reactive transport — mounting, refreshing, polling — is handled by the generic
 * {@see \Atrium\Twig\Components\Widget} host, mirroring how Tables and Forms split
 * a descriptor from its Live Component. Subclasses are autoconfigured via the
 * `atrium.widget` tag and resolved through {@see WidgetRegistry}.
 *
 * This signature is part of the public API contract — treat changes as
 * BC-relevant.
 */
abstract class Widget
{
    /**
     * Scalar context supplied where the widget is embedded (ids, ranges, filter
     * values). Never a hydrated entity — re-fetch from your injected services.
     *
     * @var array<string, scalar|array<array-key, scalar|null>|null>
     */
    protected array $params = [];

    /**
     * Return a copy bound to the given embed context. The host calls this once per
     * render; because widgets are shared services, binding clones rather than
     * mutates so two embeds of the same widget cannot clobber each other.
     *
     * @param array<string, scalar|array<array-key, scalar|null>|null> $params
     */
    public function withParams(array $params): static
    {
        $clone = clone $this;
        $clone->params = $params;

        return $clone;
    }

    /**
     * Authorization gate, enforced server-side on mount and on every refresh. A
     * widget that returns false renders nothing and is skipped in a grid. Override
     * to integrate the host app's security (it may use the widget's injected
     * services).
     */
    public function canView(): bool
    {
        return true;
    }

    /**
     * Grid width when placed in a dashboard (or any grid). An int spans N columns
     * at `lg`+; `'full'` always spans the row; a per-breakpoint map (e.g.
     * `['md' => 2, 'xl' => 3]`) gives responsive control.
     *
     * @return int|string|array<string, int>
     */
    public function getColumnSpan(): int|string|array
    {
        return 1;
    }

    /**
     * Auto-refresh cadence (e.g. `'10s'`, `'500ms'`, `'2m'`); null disables
     * polling. Each tick re-renders only this widget and recomputes its data.
     */
    public function getPollingInterval(): ?string
    {
        return null;
    }

    /**
     * Template that renders this widget kind. Set by each concrete widget family
     * (stats, chart); integrators do not normally override it.
     *
     * @internal
     */
    abstract public function getView(): string;

    /**
     * Tailwind column-span classes derived from {@see getColumnSpan()}.
     *
     * @internal
     */
    public function getColumnSpanClass(): string
    {
        $span = $this->getColumnSpan();

        if (\is_int($span)) {
            return 'lg:col-span-'.$span;
        }

        if (\is_string($span)) {
            return 'full' === $span ? 'col-span-full' : '';
        }

        $classes = [];
        foreach ($span as $breakpoint => $count) {
            $classes[] = $breakpoint.':col-span-'.$count;
        }

        return implode(' ', $classes);
    }
}
