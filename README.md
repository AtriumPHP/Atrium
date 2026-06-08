# Atrium

A PHP-configured, modular, open-source **admin panel framework for Symfony**.
Describe an admin interface entirely in PHP (resources, columns, fields,
actions) and get a polished, reactive UI with **no JavaScript build step and no
separate API**.
Reactivity is delivered server-side via Symfony UX Live Components; styling via
Tailwind.

![The Atrium admin panel: a dashboard with stat widgets and a bar chart, configured entirely in PHP](docs/assets/dashboard.png)

## Features

- **Server-driven, zero-JS-build reactivity.** Search, sort, filter, inline
  validation, dependent fields, modals and toasts all run through Symfony UX Live
  Components — no React/Vue, no JSON API, no asset pipeline for the consumer.
- **Resources, auto-discovered.** One PHP class per entity describes its table,
  form, pages and abilities; Atrium finds it (no tags, no YAML) and serves full
  CRUD. Inline small resources, or split into `Tables/`, `Schemas/`, `Pages/`.
- **Tables.** Searchable / sortable / paginated lists with **filters**, **relation
  (dotted-path) columns** (`author.name`), row / header / **bulk** actions, column
  formatting (badge, boolean, money, alignment, width), configurable empty states,
  default sort and page-size selector.
- **Forms.** A fluent field schema (text, number, select with dependent
  `optionsUsing`, checkbox, toggle, radio, date/time, colour, **tags**,
  **key-value**, …) on a real **layout tree** — `Grid` / `Section` / `Fieldset` /
  `Flex`, plus **Tabs** and multi-step **Wizards** — with conditional visibility,
  cross-field reactivity (`afterStateUpdated`) and Symfony-constraint validation.
- **Actions.** View-agnostic row / header / bulk / page actions with no-JS
  confirmation modals and dropdown groups; built-in View/Edit/Delete/bulk-delete
  plus custom server actions, all authorizable.
- **Record View (infolists).** Read-only View screens declared with an **entry
  family** — Text, Icon, Image, Colour, KeyValue, Code, Repeatable — laid out with
  the very same layout components as forms.
- **Relations & nesting.** **Relation managers** (one-to-many and many-to-many with
  a server-driven tab strip) for create/edit/delete, associate/dissociate and
  attach/detach, plus **nested resources** scoped under a parent record
  (`/admin/course/12/lesson/…`).
- **Dashboards & widgets.** Routable dashboards arranging **stat cards** and
  **Chart.js charts** with the same layout primitives; widgets are embeddable
  anywhere and refresh independently (manual or polling).
- **Notifications.** Server-driven **toasts** raised from any component or
  controller, over a live channel (no reload) or a flash bridge (surviving a
  redirect), with optional in-toast link/event actions.
- **Authorization & scoping.** Per-resource / per-action gates plus
  `scopeQuery()` for multi-tenancy, ownership or soft-deletes — applied to lists,
  counts, bulk actions **and** single-record resolution.
- **Lifecycle hooks & atomic saves.** Record, form-validation and action lifecycle
  hooks; bring your own persistence (`handleRecordCreation` / a command bus) inside
  a single transaction.
- **Self-contained & storage-agnostic.** Ships precompiled Tailwind CSS and an icon
  set (Symfony UX Icons / Lucide, any Iconify name) — no Tailwind config required.
  The core holds **no Doctrine types**; data access goes through a swappable
  `DataProvider` (Doctrine adapter included).

> **Status: pre-1.0, under active development.** Everything above is implemented
> and tested; the public API may still change in a 0.x minor (changes are flagged).
> See the **[integration guide](docs/integration-guide/)** to build with it, the
> [`CHANGELOG`](CHANGELOG.md) for what's landed in each release, and
> [`docs/PRDs/PRD.md`](docs/PRDs/PRD.md) for the roadmap.

## Documentation

The **[integration guide](docs/integration-guide/)** is the place to start —
[install and build your first resource](docs/integration-guide/getting-started.md),
then dive into [resources](docs/integration-guide/resources/overview.md),
[tables](docs/integration-guide/tables/table-configuration.md),
[forms](docs/integration-guide/forms/overview.md),
[actions](docs/integration-guide/actions/overview.md) and
[data](docs/integration-guide/data/providers.md).

## Requirements

- PHP >= 8.4
- Symfony 8.1

## Installation

```bash
composer require atriumphp/atrium
```

Register the bundle (Symfony Flex does this automatically; otherwise add it to
`config/bundles.php`):

```php
return [
    // ...
    Atrium\AtriumBundle::class => ['all' => true],
];
```

## Defining a resource

A resource is one PHP class per entity — point it at the entity, describe the
table and the form, and Atrium discovers it automatically (no tags, no YAML) and
serves a full CRUD screen:

```php
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;

final class TagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([
            Column::make('name')->sortable()->searchable(),
            Column::make('slug'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([
            TextField::make('name')->required(),
            TextField::make('slug')->required(),
        ]);
    }
}
```

That's a searchable, sortable, paginated list with Edit/New actions and a
validated create/edit form, at `/admin/tag`. Non-trivial resources delegate to
dedicated `Tables/`, `Schemas/` and `Pages/` classes; small ones inline as above.
See the [getting-started guide](docs/integration-guide/getting-started.md) for the
full walkthrough.

## Quality gates

```bash
composer test       # PHPUnit
composer phpstan    # PHPStan (max)
composer cs         # PHP-CS-Fixer (Symfony ruleset), dry-run check
composer cs:fix     # PHP-CS-Fixer, apply
```

## License

[MIT](LICENSE).
