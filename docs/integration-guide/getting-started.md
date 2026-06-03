# Getting started

> Install Atrium, mount the panel, and build your first resource — a working
> admin screen in a few minutes, configured entirely in PHP.

## What Atrium is

Atrium is a PHP-configured admin panel framework for Symfony. You describe each
screen — its table, its form, its actions — as a small PHP class, and Atrium
renders a polished, reactive UI. There is **no JavaScript build step** for your
app and **no separate API**: reactivity is delivered server-side by Symfony UX
Live Components, and styling ships precompiled with the bundle.

If you have used Filament in the Laravel world, the shape will feel familiar; the
mechanics are pure Symfony (Doctrine, the validator, Twig, UX).

## Requirements

- PHP **8.4+**
- Symfony **8.1+**
- For the default data layer: Doctrine ORM (auto-detected — see [Data](data/)).
- For the default styling: Symfony AssetMapper (the bundle ships precompiled CSS).

## Installation

```bash
composer require atriumphp/atrium
```

### 1. Register the bundle

Symfony Flex registers it for you. Otherwise add it to `config/bundles.php`:

```php
return [
    // ...
    Atrium\AtriumBundle::class => ['all' => true],
];
```

### 2. Mount the panel routes

Add a route import so the panel's parametric routes are loaded:

```yaml
# config/routes/atrium.yaml
atrium:
    resource: '@AtriumBundle/config/routes.php'
```

A small fixed set of routes (`/admin`, `/admin/{resource}`,
`/admin/{resource}/new`, `/admin/{resource}/{id}/edit`) covers **every** resource
— adding a CRUD resource never requires touching routing.

### 3. (Optional) Configure the panel

The defaults work out of the box. To change the URL prefix or the brand label:

```yaml
# config/packages/atrium.yaml
atrium:
    path_prefix: '/admin'   # where the panel is mounted (default: /admin)
    brand: 'Acme Admin'     # shown in the panel shell (default: Atrium)
```

### 4. Styling

The bundle exposes a precompiled stylesheet through AssetMapper, so the panel is
styled with no Tailwind setup in your app. If you use AssetMapper (the Symfony
default), there is nothing to do. Apps that don't use AssetMapper can override the
layout's `stylesheets` Twig block to load the CSS another way.

## Your first resource

A resource is one class per entity. Point it at your entity, describe the table
and the form, and Atrium discovers it automatically (no tags, no YAML).

```php
namespace App\Admin;

use App\Entity\Tag;
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

That's a full CRUD screen: a searchable, sortable, paginated list with **Edit**
and **New** actions already wired, and a create/edit form with validation. Visit
`/admin/tag` to see it.

### How discovery works

Any class extending `AdminResource` is auto-registered (via autoconfiguration),
keyed by a **slug** derived from the entity name (`Tag` → `tag`). The slug is the
URL segment and the registry key; override [`getSlug()`](resources/overview.md) to
change it. Resources are services, so you can inject dependencies (a repository,
`Security`, a tenant context) through the constructor.

## Where to go next

- **[Resources](resources/overview.md)** — the class that ties a screen together,
  plus [authorization](resources/authorization.md),
  [lifecycle hooks](resources/lifecycle-hooks.md) and
  [navigation](resources/navigation.md).
- **[Tables](tables/columns.md)** — columns (including relation columns), search,
  sort, pagination, filters.
- **[Forms](forms/overview.md)** — fields, layout, tabs and wizards, validation,
  reactive fields.
- **[Actions](actions/overview.md)** — row, header and bulk actions.
- **[Data](data/providers.md)** — the storage abstraction and how to swap it.
```
