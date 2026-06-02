# Atrium

A PHP-configured, modular, open-source **admin panel framework for Symfony** —
the spiritual shape of Filament, built for the Symfony ecosystem. Describe an
admin interface entirely in PHP (resources, columns, fields, actions) and get a
polished, reactive UI with **no JavaScript build step and no separate API**.
Reactivity is delivered server-side via Symfony UX Live Components; styling via
Tailwind.

> **Status: Phase 0 boilerplate.** This package currently contains the bundle
> skeleton, the core value objects/contracts, and the engineering harness. The
> reactive panel, tables, forms, actions, infolists, widgets and notifications
> arrive in subsequent phases. See [`docs/PRD.md`](docs/PRD.md) for the full
> roadmap and [`CLAUDE.md`](CLAUDE.md) for the conventions.

## Requirements

- PHP >= 8.4
- Symfony 8.1

## Installation

```bash
composer require atrium/atrium
```

Register the bundle (Symfony Flex does this automatically; otherwise add it to
`config/bundles.php`):

```php
return [
    // ...
    Atrium\AtriumBundle::class => ['all' => true],
];
```

## Defining a resource (target DX)

The framework's promise is "configured in PHP like Filament". Small resources
inline their configuration:

```php
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;

final class TagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function columns(): array
    {
        return [
            Column::make('name')->sortable()->searchable(),
            Column::make('slug'),
        ];
    }
}
```

Resources are auto-discovered via autoconfiguration — no manual registration,
tags, or YAML. Non-trivial resources delegate to dedicated `Tables/`, `Schemas/`
and `Pages/` classes (see the PRD §9b); that layout lands with the forms phase.

## What's in the box today

| Component | Description |
| --- | --- |
| `Atrium\AtriumBundle` | The bundle: configuration + autoconfiguration wiring. |
| `Atrium\Resource\AdminResource` | Abstract base for resources (public API contract). |
| `Atrium\Resource\ResourceRegistry` | Slug + class lookup, fed by the `atrium.resource` tag. |
| `Atrium\Table\Column` | Fluent, Doctrine-agnostic column value object. |
| `Atrium\DataProvider\DataProviderInterface` | Backend-agnostic read contract. |
| `Atrium\DataProvider\DoctrineDataProvider` | Default Doctrine ORM adapter (stub). |

## Quality gates

```bash
composer test       # PHPUnit
composer phpstan    # PHPStan (max)
composer cs         # PHP-CS-Fixer (Symfony ruleset), dry-run check
composer cs:fix     # PHP-CS-Fixer, apply
```

## License

[MIT](LICENSE).
