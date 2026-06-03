<?php

declare(strict_types=1);

namespace Atrium\View;

use Atrium\Action\Action;
use Atrium\Layout\Component;
use Atrium\Layout\Concern\HasColumnSpan;
use Atrium\Layout\Concern\HasGrow;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * The read-only sibling of a form {@see \Atrium\Form\Field\Field}: a
 * {@see Component} leaf that resolves **state** from the record (by name, with
 * dot-notation for relations/JSON — `TextEntry::make('author.name')`) and renders
 * it as display markup. Entries live in the same {@see \Atrium\Form\Schema} /
 * layout tree as fields, so they compose with `Section`/`Grid`/`Fieldset`/`Tabs`.
 *
 * The base carries the full shared configuration surface — labelling, layout,
 * record-aware visibility, state resolution, annotation, links — and every
 * closure option is evaluated against the loaded record. Concrete entries
 * ({@see TextEntry}, …) add their own type-specific formatting via
 * {@see viewExtras()}.
 */
abstract class Entry implements Component
{
    use HasColumnSpan;
    use HasGrow;

    private static ?PropertyAccessorInterface $sharedAccessor = null;

    protected ?string $label = null;
    protected bool $hiddenLabel = false;
    protected bool $inlineLabel = false;
    protected string $align = 'start';

    /** @var array<string, string> */
    protected array $extraAttributes = [];

    protected mixed $state = null;
    protected bool $hasExplicitState = false;
    protected ?\Closure $getStateUsing = null;
    protected ?\Closure $formatStateUsing = null;
    protected mixed $default = null;
    protected string|\Closure|null $placeholder = null;

    protected bool|\Closure $visible = true;

    protected string|\Closure|null $tooltip = null;
    protected string|\Closure|null $helperText = null;
    protected string|\Closure|null $hint = null;
    protected ?string $hintIcon = null;
    protected ?string $hintColor = null;
    protected string|\Closure|null $icon = null;
    protected string $iconPosition = 'before';

    protected string|\Closure|null $url = null;
    protected bool $openInNewTab = false;

    /** @var list<Action> */
    protected array $prefixActions = [];
    /** @var list<Action> */
    protected array $suffixActions = [];
    /** @var list<Action> */
    protected array $hintActions = [];

    final public function __construct(
        protected readonly string $name,
    ) {
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    // -- Labelling & layout ---------------------------------------------------

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function hiddenLabel(bool $hidden = true): static
    {
        $this->hiddenLabel = $hidden;

        return $this;
    }

    public function inlineLabel(bool $inline = true): static
    {
        $this->inlineLabel = $inline;

        return $this;
    }

    /**
     * One of: start, center, end, right.
     */
    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function extraAttributes(array $attributes): static
    {
        $this->extraAttributes = $attributes;

        return $this;
    }

    // -- State ----------------------------------------------------------------

    public function state(mixed $value): static
    {
        $this->state = $value;
        $this->hasExplicitState = true;

        return $this;
    }

    public function getStateUsing(\Closure $resolver): static
    {
        $this->getStateUsing = $resolver;

        return $this;
    }

    public function formatStateUsing(\Closure $formatter): static
    {
        $this->formatStateUsing = $formatter;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function placeholder(string|\Closure $text): static
    {
        $this->placeholder = $text;

        return $this;
    }

    // -- Visibility (record-aware) -------------------------------------------

    public function visible(bool|\Closure $condition = true): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function hidden(bool|\Closure $condition = true): static
    {
        $this->visible = $condition instanceof \Closure
            ? static fn (object $record): bool => !$condition($record)
            : !$condition;

        return $this;
    }

    // -- Annotation -----------------------------------------------------------

    public function tooltip(string|\Closure $text): static
    {
        $this->tooltip = $text;

        return $this;
    }

    public function helperText(string|\Closure $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function hint(string|\Closure $text): static
    {
        $this->hint = $text;

        return $this;
    }

    public function hintIcon(string $icon): static
    {
        $this->hintIcon = $icon;

        return $this;
    }

    public function hintColor(string $color): static
    {
        $this->hintColor = $color;

        return $this;
    }

    public function icon(string|\Closure $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * One of: before, after.
     */
    public function iconPosition(string $position): static
    {
        $this->iconPosition = $position;

        return $this;
    }

    // -- As a link ------------------------------------------------------------

    public function url(string|\Closure $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function openUrlInNewTab(bool $new = true): static
    {
        $this->openInNewTab = $new;

        return $this;
    }

    // -- Inline actions -------------------------------------------------------

    /**
     * @param list<Action> $actions
     */
    public function prefixActions(array $actions): static
    {
        $this->prefixActions = array_values($actions);

        return $this;
    }

    /**
     * @param list<Action> $actions
     */
    public function suffixActions(array $actions): static
    {
        $this->suffixActions = array_values($actions);

        return $this;
    }

    /**
     * @param list<Action> $actions
     */
    public function hintActions(array $actions): static
    {
        $this->hintActions = array_values($actions);

        return $this;
    }

    // -- Accessors ------------------------------------------------------------

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? self::humanise($this->name);
    }

    public function isVisibleFor(object $record): bool
    {
        return $this->visible instanceof \Closure
            ? (bool) ($this->visible)($record)
            : $this->visible;
    }

    // -- Component contract ---------------------------------------------------

    public function getChildComponents(): array
    {
        return [];
    }

    // -- Rendering ------------------------------------------------------------

    /**
     * A render-ready descriptor for this entry against a record: the shared
     * presentation bits plus the concrete entry's {@see viewExtras()}. Pure given
     * the record, so it is unit-testable without rendering.
     *
     * @return array<string, mixed>
     */
    final public function toView(object $record): array
    {
        $state = $this->resolveState($record);

        $base = [
            'visible' => $this->isVisibleFor($record),
            'label' => $this->getLabel(),
            'hiddenLabel' => $this->hiddenLabel,
            'inlineLabel' => $this->inlineLabel,
            'align' => $this->align,
            'alignClass' => $this->alignClass(),
            'placeholder' => $this->resolveText($this->placeholder, $state, $record),
            'tooltip' => $this->resolveText($this->tooltip, $state, $record),
            'helperText' => $this->resolveText($this->helperText, $state, $record),
            'hint' => $this->resolveText($this->hint, $state, $record),
            'hintIcon' => $this->hintIcon,
            'hintColor' => $this->hintColor,
            'icon' => $this->icon instanceof \Closure ? ($this->icon)($state, $record) : $this->icon,
            'iconPosition' => $this->iconPosition,
            'url' => $this->url instanceof \Closure ? ($this->url)($record) : $this->url,
            'openInNewTab' => $this->openInNewTab,
            'extraAttributes' => $this->extraAttributes,
        ];

        return [...$base, ...$this->viewExtras($state, $record)];
    }

    /**
     * Type-specific descriptor keys (the formatted value, badge/colour flags, …).
     *
     * @return array<string, mixed>
     */
    abstract protected function viewExtras(mixed $state, object $record): array;

    /**
     * The raw state: an explicit `state()`, a `getStateUsing()` closure, or the
     * record's value at `name` (dot-notation aware), falling back to `default()`.
     */
    protected function resolveState(object $record): mixed
    {
        if ($this->hasExplicitState) {
            return $this->state;
        }

        if (null !== $this->getStateUsing) {
            return ($this->getStateUsing)($record);
        }

        $accessor = self::accessor();
        $value = $accessor->isReadable($record, $this->name)
            ? $accessor->getValue($record, $this->name)
            : null;

        return $value ?? $this->default;
    }

    /**
     * Apply `formatStateUsing()` if set; otherwise return the state unchanged.
     */
    protected function applyFormatter(mixed $state, object $record): mixed
    {
        return null !== $this->formatStateUsing
            ? ($this->formatStateUsing)($state, $record)
            : $state;
    }

    private function resolveText(string|\Closure|null $value, mixed $state, object $record): ?string
    {
        if ($value instanceof \Closure) {
            $value = $value($state, $record);
        }

        return match (true) {
            null === $value => null,
            \is_string($value) => $value,
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }

    private function alignClass(): string
    {
        return match ($this->align) {
            'center' => 'text-center',
            'end', 'right' => 'text-right',
            default => 'text-left',
        };
    }

    private static function accessor(): PropertyAccessorInterface
    {
        return self::$sharedAccessor ??= PropertyAccess::createPropertyAccessor();
    }

    /**
     * Humanise an attribute name (the last segment of a dotted path):
     * `author.firstName` → `First name`.
     */
    private static function humanise(string $name): string
    {
        $segment = str_contains($name, '.') ? substr((string) strrchr($name, '.'), 1) : $name;
        $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', str_replace(['_', '-'], ' ', $segment)) ?? $segment;

        return ucfirst(strtolower(trim($spaced)));
    }
}
