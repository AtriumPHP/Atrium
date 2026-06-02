<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Form\Field\Field;
use Atrium\Form\Field\RepeatableField;
use Atrium\Form\Field\SelectField;
use Atrium\Form\Get;
use Atrium\Form\Schema;
use Atrium\Form\Set;
use Atrium\Layout\Component;
use Atrium\Layout\LayoutComponent;
use Atrium\Layout\Tab;
use Atrium\Layout\Tabs;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
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
 *
 * Extensible: subclass it (with its own `AsLiveComponent` name + template) to add
 * interaction on top of the same hydrate/validate/save core — see `WizardForm`.
 * Reusable internals (`collectErrors()`, `fieldsIn()`, `schema()`) and the
 * `focusContainer()` hook are `protected` for that purpose. A resource picks its
 * form component via `AdminResource::getFormComponentName()`.
 */
#[AsLiveComponent(name: 'Atrium:Form', template: '@Atrium/components/form.html.twig')]
class Form
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

    /**
     * Active tab per {@see Tabs} container, keyed by the container id (SCH-10).
     * Server-held so switching a tab — and keeping it across unrelated
     * re-renders — needs no client JavaScript.
     *
     * @var array<string, string>
     */
    #[LiveProp]
    public array $activeTabs = [];

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
        $this->saved = false;
        $resource = $this->resourceObject();
        $operation = $this->operation();

        // Let the resource normalise the raw input before validation sees it.
        // This writes back to formData (validation reads field values from it),
        // so the normalised value is also what the form shows after submit.
        $this->formData = $resource->mutateFormDataBeforeValidate($this->formData, $operation);
        $get = new Get($this->formData);

        [$normalized, $this->errors] = $this->collectErrors($this->getFields(), $get, $operation);

        if ([] !== $this->errors) {
            $this->focusErrors($this->schema()->getComponents());

            return null; // invalid: keep the last values, surface errors
        }

        // Validation passed — let the resource react to the validated data.
        $resource->afterValidate($normalized, $operation);

        if (null === $this->entityId) {
            $entity = $this->newEntity();
        } else {
            $entity = $this->loadEntity();
            if (null === $entity) {
                return null; // the record vanished between load and save — never write a blank one
            }
        }

        // Authorize server-side: the page guard can be bypassed by posting
        // straight to this Live action, so re-check the ability here.
        $authorized = 'create' === $operation ? $resource->canCreate() : $resource->canEdit($entity);
        if (!$authorized) {
            return null;
        }

        // Let the resource reshape the submitted data before it is written.
        $normalized = $resource->mutateFormDataBeforeSave($normalized, $operation);

        foreach ($this->getFields() as $field) {
            if ($field->isDisabled() || !$field->isDehydrated() || !$field->isVisible($get, $operation)) {
                continue; // disabled / dehydrated(false) / hidden fields are not persisted
            }
            if ($this->accessor->isWritable($entity, $field->getName())) {
                $this->accessor->setValue($entity, $field->getName(), $normalized[$field->getName()] ?? null);
            }
        }

        // Persist atomically: beforeSave → handle* → afterSave run in one
        // transaction, so a failing afterSave rolls the write back rather than
        // leaving a half-saved record. The resource's handle* hooks own the
        // actual write, so an app can persist through its own service.
        $isCreate = null === $this->entityId;
        $this->writer->transactional(function () use ($resource, $entity, $operation, $isCreate): void {
            $resource->beforeSave($entity, $operation);

            if ($isCreate) {
                $resource->handleRecordCreation($entity, $this->writer);
                $this->entityId = $this->readId($entity);
            } else {
                $resource->handleRecordUpdate($entity, $this->writer);
            }

            $resource->afterSave($entity, $operation);
        });

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
     * Append an empty row to a repeatable field (Tags / Key-value); the field
     * supplies the row shape so the action stays generic (FLD-06/07).
     */
    #[LiveAction]
    public function addRow(#[LiveArg] string $field): void
    {
        $target = $this->repeatable($field);
        if (null === $target) {
            return;
        }

        $rows = $target->rows($this->formData[$field] ?? null);
        $rows[] = $target->newRow();
        $this->formData[$field] = $rows;
        $this->previousFormData[$field] = $rows;
    }

    /**
     * Remove the row at $index from a repeatable field and re-index.
     */
    #[LiveAction]
    public function removeRow(#[LiveArg] string $field, #[LiveArg] int $index): void
    {
        $target = $this->repeatable($field);
        if (null === $target) {
            return;
        }

        $rows = $target->rows($this->formData[$field] ?? null);
        unset($rows[$index]);
        $rows = array_values($rows);
        $this->formData[$field] = $rows;
        $this->previousFormData[$field] = $rows;
    }

    /**
     * Switch the active panel of a {@see Tabs} container (SCH-10).
     */
    #[LiveAction]
    public function selectTab(#[LiveArg] string $tabs, #[LiveArg] string $tab): void
    {
        $this->activeTabs[$tabs] = $tab;
    }

    /**
     * The active tab id of a container — the stored selection, or its first tab.
     */
    public function activeTab(Tabs $tabs): string
    {
        $ids = array_map(static fn (Tab $tab): string => $tab->getId(), $tabs->getTabs());
        $active = $this->activeTabs[$tabs->getId()] ?? null;

        if (null !== $active && \in_array($active, $ids, true)) {
            return $active;
        }

        return $ids[0] ?? '';
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

        // Do not expose a record's data to someone who may not edit it — the Form
        // can be mounted directly via its Live endpoint, bypassing the page guard.
        if (null !== $entity && !$this->resourceObject()->canEdit($entity)) {
            return [];
        }

        $data = [];

        foreach ($this->getFields() as $field) {
            if (null !== $entity && $this->accessor->isReadable($entity, $field->getName())) {
                $data[$field->getName()] = $field->toFormValue($this->accessor->getValue($entity, $field->getName()));
            } else {
                $data[$field->getName()] = $field->toFormValue($field->getDefault());
            }
        }

        // Let the resource reshape the data that fills the form — the record's
        // values on edit, the fields' defaults on create.
        $operation = null === $entity ? 'create' : 'edit';

        return $this->resourceObject()->mutateFormDataBeforeFill($data, $operation);
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
                if (!$component->isVisible($get, $operation)) {
                    continue;
                }

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

    /**
     * Normalise and validate a set of fields: per-field Symfony constraints plus
     * cross-field comparison rules (`same()`/`different()`, FRM-12) — the latter
     * read sibling values a standalone constraint can't see. Hidden fields are
     * skipped. Returns the normalised values and the per-field error messages.
     *
     * @param list<Field> $fields
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    protected function collectErrors(array $fields, Get $get, string $operation): array
    {
        $normalized = [];
        $errors = [];

        foreach ($fields as $field) {
            if (!$field->isVisible($get, $operation)) {
                continue; // hidden fields are not validated (FRM-08)
            }

            $value = $field->normalize($this->formData[$field->getName()] ?? null);
            $normalized[$field->getName()] = $value;

            $violations = $this->validator->validate($value, $field->getConstraints());
            if (\count($violations) > 0) {
                $errors[$field->getName()] = (string) $violations->get(0)->getMessage();
            }
        }

        $labels = [];
        foreach ($this->getFields() as $field) {
            $labels[$field->getName()] = $field->getLabel();
        }

        foreach ($fields as $field) {
            $name = $field->getName();
            if (isset($errors[$name]) || !$field->isVisible($get, $operation)) {
                continue;
            }

            foreach ($field->getComparisons() as $rule) {
                $other = $normalized[$rule['field']] ?? $this->formData[$rule['field']] ?? null;
                $equal = ($normalized[$name] ?? null) === $other;
                if (('same' === $rule['type']) === $equal) {
                    continue;
                }

                $otherLabel = $labels[$rule['field']] ?? $rule['field'];
                $errors[$name] = $rule['message'] ?? ('same' === $rule['type']
                    ? \sprintf('This value must match %s.', $otherLabel)
                    : \sprintf('This value must be different from %s.', $otherLabel));
                break;
            }
        }

        return [$normalized, $errors];
    }

    /**
     * After a failed save, reveal whichever container is hiding an errored field
     * (SCH-10), walking the whole tree. The per-container logic is the
     * {@see focusContainer()} hook so subclasses ({@see WizardForm}) can teach it
     * about their own containers.
     *
     * @param list<Component> $components
     */
    protected function focusErrors(array $components): void
    {
        foreach ($components as $component) {
            $this->focusContainer($component, array_keys($this->errors));
            $this->focusErrors($component->getChildComponents());
        }
    }

    /**
     * Reveal one container if it holds an errored field. The base handles
     * {@see Tabs} (switch to the first errored tab); override to add containers.
     *
     * @param list<string> $erroredFields
     */
    protected function focusContainer(Component $component, array $erroredFields): void
    {
        if ($component instanceof Tabs) {
            foreach ($component->getTabs() as $tab) {
                if ([] !== array_intersect($this->fieldNamesIn($tab), $erroredFields)) {
                    $this->activeTabs[$component->getId()] = $tab->getId();
                    break;
                }
            }
        }
    }

    /**
     * Field names anywhere under a layout node.
     *
     * @return list<string>
     */
    protected function fieldNamesIn(Component $node): array
    {
        $names = [];
        foreach ($this->fieldsIn($node) as $field) {
            $names[] = $field->getName();
        }

        return $names;
    }

    /**
     * Fields anywhere under a layout node.
     *
     * @return list<Field>
     */
    protected function fieldsIn(Component $node): array
    {
        $fields = [];
        foreach ($node->getChildComponents() as $child) {
            if ($child instanceof Field) {
                $fields[] = $child;

                continue;
            }
            foreach ($this->fieldsIn($child) as $nested) {
                $fields[] = $nested;
            }
        }

        return $fields;
    }

    private function repeatable(string $name): ?RepeatableField
    {
        foreach ($this->getFields() as $field) {
            if ($field->getName() === $name && $field instanceof RepeatableField) {
                return $field;
            }
        }

        return null;
    }

    protected function schema(): Schema
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
        if (null === $this->entityId) {
            return null;
        }

        // Resolve within the resource's scope: an id outside scopeQuery() is not
        // editable through this form even when posted straight to its endpoint.
        return $this->dataProvider->find(
            $this->entityClass(),
            $this->entityId,
            $this->resourceObject()->scopeFilters(),
        );
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
