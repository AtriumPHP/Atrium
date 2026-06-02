# Atrium integration guide

How to build an admin panel with Atrium. These pages are written for developers
**integrating** Atrium into a Symfony application — they document *how to use* the
public API, not how it is implemented.

> **New to Atrium?** Start with **[Getting started](getting-started.md)** — install
> the bundle, mount the panel, and build your first resource end to end. Then read
> [`resources/overview.md`](resources/overview.md): a resource is the single PHP
> class that describes how an entity appears in the panel, and everything else
> hangs off it.

## Modules

The guide is split by module (these mirror the planned package split):

| Module | Covers |
| --- | --- |
| [Getting started](getting-started.md) | Install, configure the panel, first resource |
| [`resources/`](resources/overview.md) | [`AdminResource`](resources/overview.md), [authorization](resources/authorization.md), [lifecycle hooks](resources/lifecycle-hooks.md), [navigation](resources/navigation.md) |
| [`tables/`](tables/table-configuration.md) | [Table configuration](tables/table-configuration.md), [columns](tables/columns.md) (incl. [relation columns](tables/columns.md#relation-columns)), [filters](tables/filters.md), [actions](tables/actions.md) |
| [`forms/`](forms/overview.md) | [Overview](forms/overview.md), [fields](forms/fields.md), [layout](forms/layout.md), [tabs & wizards](forms/tabs-and-wizards.md), [validation](forms/validation.md), [reactivity](forms/reactivity.md), [content](forms/content.md) |
| [`actions/`](actions/overview.md) | [`Action`, `ActionGroup`](actions/overview.md), record/header/bulk actions, built-ins |
| [`pages/`](pages/overview.md) | [`Page`](pages/overview.md) and the default List/Create/Edit pages, post-save redirects |
| [`data/`](data/providers.md) | [`DataProviderInterface`, `DataWriterInterface`, `DataQuery`](data/providers.md), [query scoping](data/query-scoping.md) |

## Page format (canonical template)

Every page in this guide follows the same shape so readers know where to look.
Copy this template when adding a page:

```markdown
# {Component or topic}

> One sentence: what it is and the problem it solves for an integrator.

## When to use

A short paragraph or a few bullets — when you reach for this, and when you don't.

## Example

​```php
// Idiomatic, copy-pasteable PHP. Show the inline-on-the-resource path first;
// add the dedicated-class variant afterwards only if it differs.
​```

## API reference

For a fluent builder, a method table:

| Method | Description |
| --- | --- |
| `make(string $name): static` | Create the builder. |
| `label(string $label): static` | Override the generated label. |

For a method with non-obvious behaviour, follow the table with a short
subsection and an example.

## See also

- [Related page](../module/topic.md)
```

### Conventions

- **Signatures are real.** Copy the exact public signature (types, defaults).
  When it changes, update the page in the same change.
- **Examples run.** Prefer snippets a reader can paste into a resource and have
  work. Reference the playground (`atrium-playground`) for full examples.
- **Document the contract, not the internals.** No private methods, no
  `@internal` types. Explain *what* and *when*, and *gotchas* that bite.
- **One file per component or cohesive topic.** Split a module into focused
  pages rather than one giant file.
- **Cross-link liberally** with relative links so readers can navigate the API.
