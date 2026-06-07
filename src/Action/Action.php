<?php

declare(strict_types=1);

namespace Atrium\Action;

/**
 * A fluent, view-agnostic action.
 *
 * The {@see Atrium\Action} subsystem is deliberately decoupled from where an
 * action appears: the same builder backs table record actions today, and will
 * back header, page and bulk actions as the panel grows — a single Actions
 * layer shared across the whole panel.
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

    /**
     * A static URL set via {@see url()} with a string. Kept separately so the
     * action can resolve a URL with no per-record subject (header/bulk bars).
     */
    protected ?string $staticUrl = null;

    protected ?\Closure $handler = null;

    protected ?string $ability = null;

    protected ?string $successNotificationTitle = null;

    protected ?string $successNotificationBody = null;

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
        if (\is_string($url)) {
            $this->staticUrl = $url;
            $this->urlResolver = static fn (object $subject): string => $url;
        } else {
            $this->urlResolver = $url;
        }

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

    /**
     * Gate this action behind a resource ability (`view`, `create`, `edit`,
     * `delete`, or a custom key). The table hides and refuses to run the action
     * unless {@see \Atrium\Resource\AdminResource::can()} allows it. The built-in
     * table actions set this for you.
     */
    public function authorize(string $ability): static
    {
        $this->ability = $ability;

        return $this;
    }

    /**
     * Raise a success toast after this server action runs (no exception). The host
     * component surfaces it on the live channel. The built-in delete actions set a
     * default; call this to override the title/body or add one to a custom action.
     */
    public function successNotification(string $title, ?string $body = null): static
    {
        $this->successNotificationTitle = $title;
        $this->successNotificationBody = $body;

        return $this;
    }

    public function getSuccessNotificationTitle(): ?string
    {
        return $this->successNotificationTitle;
    }

    public function getSuccessNotificationBody(): ?string
    {
        return $this->successNotificationBody;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAbility(): ?string
    {
        return $this->ability;
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

    /**
     * Whether this action is shown where there is no per-record subject (header
     * and bulk bars). Visibility there must be a plain bool — typically computed
     * at config time, e.g. `->visible($this->isGranted(...))`. A subject-bound
     * closure cannot be evaluated without a record, so it fails **closed** (the
     * action is hidden and cannot run): a destructive action a developer tried to
     * gate with a closure is never silently left exposed.
     */
    public function isVisible(): bool
    {
        return \is_bool($this->visible) && $this->visible;
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
     * The developer-supplied confirmation message, or null when none was set and
     * a default should be composed by the host (e.g. a count-aware bulk message).
     */
    public function getCustomConfirmationMessage(): ?string
    {
        return $this->confirmationMessage;
    }

    /**
     * The action's URL for a subject, or null if it is a server action.
     */
    public function getUrl(object $subject, ActionContext $context): ?string
    {
        return null === $this->urlResolver ? null : ($this->urlResolver)($subject);
    }

    /**
     * The action's URL with no per-record subject — for header and bulk actions,
     * which sit above the table rather than on a row. A link header action (e.g.
     * {@see \Atrium\Table\Action\CreateAction}) overrides this; a plain server
     * action returns null.
     */
    public function getStandaloneUrl(ActionContext $context): ?string
    {
        return $this->staticUrl;
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

    public function toView(object $subject, ActionContext $context, ?string $id, ?\Closure $authorize = null): array
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
            'url' => self::safeUrl($this->getUrl($subject, $context)),
            'id' => $id,
            'confirm' => $this->requiresConfirmation,
        ];
    }

    /**
     * Resolve to a render-ready descriptor with no per-record subject, for the
     * header and bulk action bars. Mirrors {@see toView()} but carries no record
     * id (the bulk host runs it against the current selection instead).
     *
     * @return array<string, mixed>
     */
    public function toStandaloneView(ActionContext $context): array
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
            'url' => self::safeUrl($this->getStandaloneUrl($context)),
            'id' => null,
            'confirm' => $this->requiresConfirmation,
        ];
    }

    /**
     * Guard a URL before it reaches an `href`: a custom `->url()` closure may
     * derive it from record data, so reject any explicit scheme other than
     * http(s)/mailto/tel (e.g. `javascript:`, `data:`); relative/anchor/query
     * paths pass. Mirrors the same guard on {@see \Atrium\View\Entry} and the
     * table row URL.
     */
    private static function safeUrl(?string $url): ?string
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        $candidate = ltrim($url);
        if (preg_match('#^(?:https?:|mailto:|tel:|/|\#|\?|\.)#i', $candidate)) {
            return $url;
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*:#i', $candidate) ? null : $url;
    }

    /**
     * @return list<Action>
     */
    public function flatten(): array
    {
        return [$this];
    }
}
