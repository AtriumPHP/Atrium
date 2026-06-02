<?php

declare(strict_types=1);

namespace Atrium\Action;

/**
 * A fluent, view-agnostic action.
 *
 * The {@see Atrium\Action} subsystem is deliberately decoupled from where an
 * action appears: the same builder backs table record actions today, and will
 * back header, page and bulk actions as the panel grows — exactly like a single
 * Actions layer is shared across a Filament app.
 *
 * An action is either a **link** (it resolves a URL via {@see url()}, or a
 * subclass like {@see EditAction}) or a **server action** (it carries a handler,
 * set via {@see action()}, that the host component runs server-side). The host
 * decides what to pass the handler — the base stays dependency-free. Label,
 * colour, icon, visibility and an optional confirmation step are common to both.
 *
 * Part of the public API contract — mirror the {@see \Atrium\Table\Column} style.
 *
 * @phpstan-consistent-constructor
 */
class Action implements ActionContract
{
    protected ?string $label = null;

    protected ?string $icon = null;

    protected string $color = 'gray';

    /** @var 'button'|'link'|'icon' */
    protected string $style = 'link';

    protected int|string|null $badge = null;

    /** @var bool|\Closure(object): bool */
    protected bool|\Closure $visible = true;

    protected bool $requiresConfirmation = false;

    protected ?string $confirmationMessage = null;

    /** @var (\Closure(object): ?string)|null */
    protected ?\Closure $urlResolver = null;

    protected ?\Closure $handler = null;

    protected function __construct(
        protected readonly string $name,
    ) {
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Semantic colour key: gray, primary, red, green, amber or sky.
     */
    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Render the trigger as a filled button.
     */
    public function button(): static
    {
        $this->style = 'button';

        return $this;
    }

    /**
     * Render the trigger as a text link (the default).
     */
    public function link(): static
    {
        $this->style = 'link';

        return $this;
    }

    /**
     * Render the trigger as an icon-only button (the label becomes its tooltip).
     */
    public function iconButton(): static
    {
        $this->style = 'icon';

        return $this;
    }

    /**
     * A small badge shown on the trigger (e.g. a count or a short label).
     */
    public function badge(int|string|null $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * Show the action only when the callback returns true for the subject (or
     * pass a bool to toggle it outright).
     *
     * @param bool|\Closure(object): bool $visible
     */
    public function visible(bool|\Closure $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @param bool|\Closure(object): bool $hidden
     */
    public function hidden(bool|\Closure $hidden = true): static
    {
        $this->visible = \is_bool($hidden) ? !$hidden : static fn (object $subject): bool => !$hidden($subject);

        return $this;
    }

    public function requiresConfirmation(bool $requiresConfirmation = true): static
    {
        $this->requiresConfirmation = $requiresConfirmation;

        return $this;
    }

    public function confirmationMessage(string $message): static
    {
        $this->confirmationMessage = $message;
        $this->requiresConfirmation = true;

        return $this;
    }

    /**
     * Make this a link action. Pass a static URL or a callback of the subject.
     *
     * @param string|\Closure(object): ?string $url
     */
    public function url(string|\Closure $url): static
    {
        $this->urlResolver = \is_string($url) ? static fn (object $subject): string => $url : $url;

        return $this;
    }

    /**
     * Make this a server action: the host runs the handler server-side, passing
     * the subject (and whatever else that host provides, e.g. a data writer).
     */
    public function action(\Closure $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name) ?? $this->name));
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * @return 'button'|'link'|'icon'
     */
    public function getStyle(): string
    {
        return $this->style;
    }

    public function getBadge(): int|string|null
    {
        return $this->badge;
    }

    public function isVisibleFor(object $subject): bool
    {
        return \is_bool($this->visible) ? $this->visible : ($this->visible)($subject);
    }

    public function needsConfirmation(): bool
    {
        return $this->requiresConfirmation;
    }

    public function getConfirmationMessage(): string
    {
        return $this->confirmationMessage ?? 'Are you sure you want to '.lcfirst($this->getLabel()).' this record?';
    }

    /**
     * The action's URL for a subject, or null if it is a server action.
     */
    public function getUrl(object $subject, ActionContext $context): ?string
    {
        return null === $this->urlResolver ? null : ($this->urlResolver)($subject);
    }

    public function isServerAction(): bool
    {
        return null !== $this->handler;
    }

    /**
     * The server-side handler, or null for a link action. The host invokes it
     * with the subject (and any host-specific arguments).
     */
    public function getHandler(): ?\Closure
    {
        return $this->handler;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/action.html.twig';
    }

    public function toView(object $subject, ActionContext $context, ?string $id): array
    {
        return [
            'kind' => 'action',
            'template' => $this->getTemplate(),
            'name' => $this->name,
            'label' => $this->getLabel(),
            'icon' => $this->icon,
            'color' => $this->color,
            'style' => $this->style,
            'badge' => $this->badge,
            'url' => $this->getUrl($subject, $context),
            'id' => $id,
            'confirm' => $this->requiresConfirmation,
        ];
    }

    /**
     * @return list<Action>
     */
    public function flatten(): array
    {
        return [$this];
    }
}
