<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Form\Field\Field;
use Atrium\Form\Field\SelectField;
use Atrium\Form\Get;
use Atrium\Form\Schema;
use Atrium\Form\Set;
use Atrium\Layout\Component;
use Atrium\Layout\LayoutComponent;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PreReRender;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The reactive create/edit form (FRM-03..06).
 *
 * Field values live in a single writable `formData` LiveProp; live() fields
 * re-render the component so dependents (e.g. a dependent select) update.
 * Saving normalises + validates each field, persists through the writer, and
 * either redirects (when a page supplies a URL) or shows a success notice.
 */
#[AsLiveComponent(name: 'Atrium:Form', template: '@Atrium/components/form.html.twig')]
final class Form
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $resource = '';

    #[LiveProp]
    public ?string $entityId = null;

    #[LiveProp]
    public ?string $redirectAfterSave = null;

    /** @var array<string, mixed> */
    #[LiveProp(writable: true)]
    public array $formData = [];

    /**
     * Snapshot of formData from the previous render, used to detect which field
     * changed and run its afterStateUpdated() callback (FRM-10). A sub-path
     * model write (`formData[name]`) can't be caught by an `onUpdated` hook with
     * dynamic field names, so we diff in a PreReRender pass instead.
     *
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $previousFormData = [];

    /** @var array<string, string> */
    #[LiveProp]
    public array $errors = [];

    #[LiveProp]
    public bool $saved = false;

    private ?Schema $schemaCache = null;

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly DataWriterInterface $writer,
        private readonly ValidatorInterface $validator,
        private readonly PropertyAccessorInterface $accessor,
    ) {
    }

    public function mount(string $resource, ?string $entityId = null, ?string $redirectAfterSave = null): void
    {
        $this->resource = $resource;
        $this->entityId = $entityId;
        $this->redirectAfterSave = $redirectAfterSave;
        $this->formData = $this->initialFormData();
        $this->previousFormData = $this->formData;
    }

    #[LiveAction]
    public function save(): ?Response
    {
        $this->errors = [];
        $this->saved = false;
        $normalized = [];
        $get = new Get($this->formData);
        $operation = $this->operation();

        foreach ($this->getFields() as $field) {
            if (!$field->isVisible($get, $operation)) {
                continue; // hidden fields are not validated (FRM-08)
            }

            $value = $field->normalize($this->formData[$field->getName()] ?? null);
            $normalized[$field->getName()] = $value;

            $violations = $this->validator->validate($value, $field->getConstraints());
            if (\count($violations) > 0) {
                $this->errors[$field->getName()] = (string) $violations->get(0)->getMessage();
            }
        }

        if ([] !== $this->errors) {
            return null; // invalid: keep the last values, surface errors
        }

        $entity = $this->loadEntity() ?? $this->newEntity();

        foreach ($this->getFields() as $field) {
            if ($field->isDisabled() || !$field->isVisible($get, $operation)) {
                continue; // hidden fields are not persisted
            }
            if ($this->accessor->isWritable($entity, $field->getName())) {
                $this->accessor->setValue($entity, $field->getName(), $normalized[$field->getName()]);
            }
        }

        if (null === $this->entityId) {
            $this->writer->create($entity);
            $this->entityId = $this->readId($entity);
        } else {
            $this->writer->update($entity);
        }

        $this->saved = true;

        if (null !== $this->redirectAfterSave) {
            return new RedirectResponse($this->redirectAfterSave);
        }

        return null;
    }

    /**
     * The schema tree for rendering, with fields hidden by `visible()`/
     * `hiddenOn()` filtered out (and containers left empty by them dropped).
     *
     * @return list<Component>
     */
    public function getComponents(): array
    {
        return $this->filterVisible(
            $this->schema()->getComponents(),
            new Get($this->formData),
            $this->operation(),
        );
    }

    /**
     * The current form operation: `create` (new record) or `edit` (FRM-09).
     */
    public function operation(): string
    {
        return null === $this->entityId ? 'create' : 'edit';
    }

    /**
     * Before each re-render, run the `afterStateUpdated()` callback (FRM-10) of
     * every field whose value changed since the last render, handing it the new
     * value, a {@see Get} and a {@see Set} that writes back to `formData`.
     */
    #[PreReRender]
    public function runReactiveCallbacks(): void
    {
        $get = new Get($this->formData);
        $set = new Set(function (string $key, mixed $value): void {
            $this->formData[$key] = $value;
        });

        foreach ($this->getFields() as $field) {
            if (!$field->hasAfterStateUpdated()) {
                continue;
            }

            $name = $field->getName();
            $before = $this->previousFormData[$name] ?? null;
            $after = $this->formData[$name] ?? null;
            if ($before === $after) {
                continue;
            }

            $field->runAfterStateUpdated($after, $get, $set);
        }

        $this->previousFormData = $this->formData;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->schema()->getFields();
    }

    public function getValue(string $name): mixed
    {
        return $this->formData[$name] ?? '';
    }

    public function getError(string $name): ?string
    {
        return $this->errors[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function optionsFor(SelectField $field): array
    {
        return $field->getOptions($this->formData);
    }

    public function isEdit(): bool
    {
        return null !== $this->entityId;
    }

    public function getResourceLabel(): string
    {
        return $this->resourceObject()->getLabel();
    }

    /**
     * @return array<string, mixed>
     */
    private function initialFormData(): array
    {
        $entity = $this->loadEntity();
        $data = [];

        foreach ($this->getFields() as $field) {
            if (null !== $entity && $this->accessor->isReadable($entity, $field->getName())) {
                $data[$field->getName()] = $field->toFormValue($this->accessor->getValue($entity, $field->getName()));
            } else {
                $data[$field->getName()] = $field->toFormValue($field->getDefault());
            }
        }

        return $data;
    }

    /**
     * Recursively drop hidden fields and any container they leave empty. Layout
     * containers are cloned so the cached schema is never mutated.
     *
     * @param list<Component> $components
     *
     * @return list<Component>
     */
    private function filterVisible(array $components, Get $get, string $operation): array
    {
        $visible = [];

        foreach ($components as $component) {
            if ($component instanceof Field) {
                if ($component->rendersInLayout() && $component->isVisible($get, $operation)) {
                    $visible[] = $component;
                }

                continue;
            }

            if ($component instanceof LayoutComponent) {
                $children = $this->filterVisible($component->getChildComponents(), $get, $operation);
                if ([] === $children) {
                    continue;
                }

                $clone = clone $component;
                $clone->schema($children);
                $visible[] = $clone;

                continue;
            }

            $visible[] = $component; // content nodes are always visible (M2)
        }

        return $visible;
    }

    private function schema(): Schema
    {
        return $this->schemaCache ??= $this->resourceObject()->form(new Schema());
    }

    private function resourceObject(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }

    /**
     * @return class-string
     */
    private function entityClass(): string
    {
        return $this->resourceObject()->getEntityClass();
    }

    private function loadEntity(): ?object
    {
        return null === $this->entityId
            ? null
            : $this->dataProvider->find($this->entityClass(), $this->entityId);
    }

    private function newEntity(): object
    {
        $class = $this->entityClass();

        return new $class();
    }

    private function readId(object $entity): ?string
    {
        if (!$this->accessor->isReadable($entity, 'id')) {
            return null;
        }

        $id = $this->accessor->getValue($entity, 'id');

        return \is_scalar($id) ? (string) $id : null;
    }
}
