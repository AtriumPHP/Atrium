# CLAUDE.md — Working conventions for Atrium

This file primes Claude Code for work in this repository. Read it before making
changes. The full product/technical spec is in `docs/PRD.md`; this file is the
short, always-applicable rulebook.

## What this project is

Atrium is a **PHP-configured, modular admin panel framework for Symfony** — the
Filament-style developer experience, built on **Symfony UX Live Components** for
server-driven reactivity and **Tailwind** for styling. Developers configure
everything in PHP; there is **no JavaScript build step for consumers and no
separate API**.

## Non-negotiable architectural rules

1. **Server-driven, not SPA.** Reactivity is Live Components (server round-trip +
   DOM morph). Do not introduce React/Vue/a client framework or a required JSON
   API into the core. (React "islands" are a possible *future* opt-in for
   isolated widgets only — not in v1.)
2. **Dependencies point downward only.** Panel → builders (Tables, Forms,
   Actions, …) → foundation/contracts. **Never** sideways between builder
   packages. Circular package deps are the #1 risk to the planned monorepo split;
   reject any change that introduces one.
3. **No Doctrine types in core.** Core abstractions (`AdminResource`, `Column`,
   future `Field`, `DataProviderInterface`) must not reference Doctrine classes.
   Doctrine lives only in the `DoctrineDataProvider` adapter. All data access
   goes through `DataProviderInterface`.
4. **The Resource API is a stable contract.** The developer-facing signatures in
   `docs/PRD.md` §9 (`AdminResource`, `Column::make()->…`, future `form()` /
   `table()` / `pages()`) are public API. Treat changes to them as
   BC-relevant; flag them explicitly in your summary and in `CHANGELOG.md`.
5. **Pages are controller/descriptor classes, not Live Components.** A Page owns
   its route, header actions, and hooks (e.g. redirect-after-save). The reactive
   widgets *inside* a page (table, form) are the Live Components. Do not turn
   Pages into Live Components.
6. **Keep the simple inline path working.** The multi-class resource layout
   (`Pages/`, `Schemas/`, `Tables/`) is the default for non-trivial resources and
   what the maker scaffolds, but inlining `table()` / `form()` on the resource
   must remain valid for small cases.

## Naming & namespaces

- Project / brand: **Atrium**
- Packagist vendor: **`atriumphp`**; metapackage `atriumphp/atrium`; future split
  packages `atriumphp/admin-core`, `atriumphp/admin-tables`, `atriumphp/admin-forms`, …
- PHP namespace root: **`Atrium\`**
- Bundle class: **`AtriumBundle`** (Symfony convention: ends in `Bundle`)
- DI tag for resources: `atrium.resource`
- Live component names: `atrium:<name>` (e.g. `atrium:data_table`)
- Route names: `atrium_<thing>` (e.g. `atrium_dashboard`, `atrium_resource`)
- Twig namespace: `@Atrium`

Do **not** use "Filament" in any package/class/marketing name, and do not imply
official Symfony endorsement (follow Symfony's trademark guidelines). This is a
flag to respect, not legal advice.

## Code standards

- `declare(strict_types=1);` in every PHP file.
- PHP 8.2+ (target 8.4 for the UX 3.x track). Use modern syntax: constructor
  promotion, enums, `match`, readonly, first-class callable syntax, attributes.
- Final classes by default; mark intentionally-extensible base classes
  `abstract` and document them.
- Mark non-public-API classes/methods `@internal`.
- Coding standard: **PHP-CS-Fixer, Symfony ruleset**. Static analysis:
  **PHPStan at max** with a tracked baseline. CI must fail on violations.
- Prefer small value objects with fluent, chainable builders (mirror the
  `Column` style across `Field`, `Action`, etc.).

## Documentation (integration guide)

Atrium is a framework: the developer-facing surface is the product. Every
**public component** (a class an integrating developer instantiates, extends, or
configures) and every **significant public method** (a fluent setter, a lifecycle
hook, an accessor an integrator relies on) MUST be documented in the integration
guide before the change is done.

- **Location:** `docs/integration-guide/{module}/{topic}.md`, where `{module}`
  is one of `resources`, `tables`, `forms`, `actions`, `pages`, `data` (these map
  to the planned package split). One file per component or cohesive topic.
- **Format:** every page follows the canonical template in
  [`docs/integration-guide/README.md`](docs/integration-guide/README.md) — a
  one-line summary, a "When to use", a copy-pasteable PHP example, and an **API
  reference** that lists each public method with its signature and a one-line
  description (a method table for fluent builders; prose for concepts). Keep
  examples runnable and idiomatic; show the inline-on-the-resource path first.
- **Audience:** developers integrating Atrium into a Symfony app — not
  contributors. Document *how to use* the API, not how it is implemented.
- `@internal` classes/methods are out of scope (they are not public API).
- The guide is the source of truth for the public surface; when a signature
  changes, update its page in the same change and flag it in `CHANGELOG.md`.

## Commands (wire these up in Phase 1 if absent)

```bash
composer test        # PHPUnit
composer phpstan      # PHPStan analyse (max level)
composer cs           # PHP-CS-Fixer dry-run (check)
composer cs:fix       # PHP-CS-Fixer apply
composer validate     # composer.json validation
```

Run `composer test && composer phpstan && composer cs` before considering any
phase done.

## How to work through the build

- The plan is **phased** (`docs/PRD.md` §10). Implement **one phase per session**.
- **Phase 1 (engineering harness) comes before any new feature.** Do not start
  Forms/Actions until tests + PHPStan + CS + CI exist and are green.
- Every functional requirement has an ID (e.g. `TBL-03`, `FRM-05`). Reference the
  relevant IDs in commit messages and PR descriptions.
- After each phase: tests green, PHPStan clean, CS clean, `CHANGELOG.md` updated,
  README/docs touched if the public surface changed.
- **Dogfood.** Build against a real admin need, not a toy entity. If an API feels
  awkward to use, that is a design bug — surface it, don't paper over it.
- **Do not split the monorepo early.** Develop as the single `atriumphp/atrium`
  bundle; extraction into packages is Phase 7, once the seams are proven.

## Definition of done (per change)

- [ ] Code reviewed by a senior engineer
- [ ] Tests added/updated and passing
- [ ] PHPStan clean at max (baseline only for pre-existing debt)
- [ ] PHP-CS-Fixer clean
- [ ] `declare(strict_types=1)` present; `@internal` where appropriate
- [ ] No Doctrine types leaked into core; no sideways package deps introduced
- [ ] Public Resource API changes flagged + `CHANGELOG.md` updated
- [ ] Docs/README updated if the developer-facing surface changed
- [ ] Integration guide updated: every new/changed public component and
      significant public method documented under `docs/integration-guide/{module}/`
