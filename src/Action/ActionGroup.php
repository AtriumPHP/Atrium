<?php

declare(strict_types=1);

namespace Atrium\Action;

/**
 * A group of {@see Action}s rendered as a single dropdown trigger. Placed among
 * a host's actions to keep secondary actions tidy. The dropdown is a native
 * `<details>` element, so opening it needs no client JavaScript.
 */
final class ActionGroup implements ActionContract
{
    private ?string $label = null;

    private ?string $icon = 'ellipsis-vertical';

    private string $color = 'gray';

    /**
     * @param list<Action> $actions
     */
    private function __construct(
        private readonly array $actions,
    ) {
    }

    /**
     * @param list<Action> $actions
     */
    public static function make(array $actions): self
    {
        return new self(array_values($actions));
    }

    public function label(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return list<Action>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getLabel(): ?string
    {
        return $this->label;
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
     * Visible when at least one of its actions is visible for the subject.
     */
    public function isVisibleFor(object $subject): bool
    {
        foreach ($this->actions as $action) {
            if ($action->isVisibleFor($subject)) {
                return true;
            }
        }

        return false;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/action_group.html.twig';
    }

    public function toView(object $subject, ActionContext $context, ?string $id, ?\Closure $authorize = null): array
    {
        $children = [];
        foreach ($this->actions as $action) {
            if ($action->isVisibleFor($subject) && (null === $authorize || $authorize($action))) {
                $children[] = $action->toView($subject, $context, $id, $authorize);
            }
        }

        return [
            'kind' => 'group',
            'template' => $this->getTemplate(),
            'label' => $this->label,
            'icon' => $this->icon,
            'color' => $this->color,
            'actions' => $children,
        ];
    }

    /**
     * @return list<Action>
     */
    public function flatten(): array
    {
        return $this->actions;
    }
}
