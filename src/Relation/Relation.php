<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Table\TableConfiguration;

/**
 * A declarative description of a resource relationship (REL-01). Configured in
 * PHP on a resource's {@see AdminResource::relations()}; the kind, target
 * resource and keys are stated explicitly so core never reads Doctrine
 * association metadata. An {@see RelationResolver} turns it into an immutable
 * {@see RelationDescriptor} for the data layer and the relation manager.
 */
final class Relation
{
    private ?RelationKind $kind = null;

    /** @var class-string<AdminResource>|null */
    private ?string $targetClass = null;

    private ?string $foreignKey = null;
    private ?string $pivotTable = null;
    private ?string $pivotParentKey = null;
    private ?string $pivotRelatedKey = null;

    /** @var list<string> */
    private array $pivotColumns = [];

    /** @var string|(\Closure(): string)|null */
    private string|\Closure|null $label = null;
    private ?string $icon = null;
    private string|\Closure|null $recordTitle = null;

    /** @var \Closure(TableConfiguration): TableConfiguration|null */
    private ?\Closure $table = null;

    /** @var \Closure(Schema): Schema|null */
    private ?\Closure $form = null;

    private bool|\Closure $visible = true;
    private bool $readOnlyOnView = true;

    /** @var class-string<RelationManagerConfiguration>|null */
    private ?string $using = null;

    private ?RelationManagerConfiguration $resolvedConfiguration = null;

    private ?string $emptyHeading = null;
    private ?string $emptyDescription = null;
    private ?string $emptyIcon = null;

    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** @param class-string<AdminResource> $target */
    public function oneToMany(string $target): self
    {
        $this->kind = RelationKind::OneToMany;
        $this->targetClass = $target;

        return $this;
    }

    /** @param class-string<AdminResource> $target */
    public function manyToMany(string $target): self
    {
        $this->kind = RelationKind::ManyToMany;
        $this->targetClass = $target;

        return $this;
    }

    public function foreignKey(string $column): self
    {
        $this->foreignKey = $column;

        return $this;
    }

    public function pivotTable(string $table): self
    {
        $this->pivotTable = $table;

        return $this;
    }

    public function pivotKeys(string $parent, string $related): self
    {
        $this->pivotParentKey = $parent;
        $this->pivotRelatedKey = $related;

        return $this;
    }

    /** @param list<string> $columns */
    public function pivotColumns(array $columns): self
    {
        $this->pivotColumns = $columns;

        return $this;
    }

    /** @param string|(\Closure(): string) $label */
    public function label(string|\Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function recordTitle(string|\Closure $attribute): self
    {
        $this->recordTitle = $attribute;

        return $this;
    }

    /** @param \Closure(TableConfiguration): TableConfiguration $configure */
    public function table(\Closure $configure): self
    {
        $this->table = $configure;

        return $this;
    }

    /** @param \Closure(Schema): Schema $configure */
    public function form(\Closure $configure): self
    {
        $this->form = $configure;

        return $this;
    }

    public function emptyState(string $heading, ?string $description = null, ?string $icon = null): self
    {
        $this->emptyHeading = $heading;
        $this->emptyDescription = $description;
        $this->emptyIcon = $icon;

        return $this;
    }

    public function visible(bool|\Closure $condition = true): self
    {
        $this->visible = $condition;

        return $this;
    }

    public function readOnlyOnView(bool $readOnly = true): self
    {
        $this->readOnlyOnView = $readOnly;

        return $this;
    }

    /** @param class-string<RelationManagerConfiguration> $class */
    public function using(string $class): self
    {
        $this->using = $class;

        return $this;
    }

    // -- Accessors ---------------------------------------------------------

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): RelationKind
    {
        return $this->kind ?? throw new \LogicException(\sprintf('Relation "%s" has no kind; call oneToMany()/manyToMany().', $this->name));
    }

    /** @return class-string<AdminResource> */
    public function getTargetClass(): string
    {
        return $this->targetClass ?? throw new \LogicException(\sprintf('Relation "%s" has no target resource.', $this->name));
    }

    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }

    public function getPivotTable(): ?string
    {
        return $this->pivotTable;
    }

    public function getPivotParentKey(): ?string
    {
        return $this->pivotParentKey;
    }

    public function getPivotRelatedKey(): ?string
    {
        return $this->pivotRelatedKey;
    }

    /** @return list<string> */
    public function getPivotColumns(): array
    {
        return $this->pivotColumns;
    }

    public function getLabel(): string
    {
        if ($this->label instanceof \Closure) {
            return (string) ($this->label)();
        }

        return $this->label ?? ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name))));
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /** Raw configured record-title attribute (null when defaulted later). */
    public function getRecordTitle(): string|\Closure|null
    {
        return $this->recordTitle;
    }

    public function applyTable(TableConfiguration $table): TableConfiguration
    {
        return null !== $this->table ? ($this->table)($table) : $table;
    }

    public function applyForm(Schema $schema): Schema
    {
        return null !== $this->form ? ($this->form)($schema) : $schema;
    }

    public function hasTable(): bool
    {
        return null !== $this->table;
    }

    public function hasForm(): bool
    {
        return null !== $this->form;
    }

    public function isReadOnlyOnView(): bool
    {
        return $this->readOnlyOnView;
    }

    public function isVisibleFor(object $parent): bool
    {
        return $this->visible instanceof \Closure ? (bool) ($this->visible)($parent) : $this->visible;
    }

    /** @return class-string<RelationManagerConfiguration>|null */
    public function getUsing(): ?string
    {
        return $this->using;
    }

    /**
     * Instantiate the dedicated configuration class set via {@see using()}, or null
     * when the relation configures its table/form inline. Memoised.
     */
    public function resolveConfiguration(): ?RelationManagerConfiguration
    {
        if (null === $this->using) {
            return null;
        }

        if (null === $this->resolvedConfiguration) {
            /** @var class-string<RelationManagerConfiguration> $class */
            $class = $this->using;
            $this->resolvedConfiguration = new $class();
        }

        return $this->resolvedConfiguration;
    }

    public function getEmptyHeading(): ?string
    {
        return $this->emptyHeading;
    }

    public function getEmptyDescription(): ?string
    {
        return $this->emptyDescription;
    }

    public function getEmptyIcon(): ?string
    {
        return $this->emptyIcon;
    }
}
