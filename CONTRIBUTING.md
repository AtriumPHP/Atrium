# Contributing to Atrium

Thanks for your interest in contributing! Atrium is in early development
(Phase 0 boilerplate); the architecture and roadmap live in
[`docs/PRD.md`](docs/PRD.md), and the working conventions in
[`CLAUDE.md`](CLAUDE.md).

## Getting started

```bash
composer install
composer test
composer phpstan
composer cs:check
```

## Ground rules

- **`declare(strict_types=1);`** in every PHP file.
- **Downward dependencies only.** The eventual package split is strict: the panel
  depends on builders; builders depend only on the foundation and shared
  contracts — never sideways between builders. Design namespaces accordingly.
- **No Doctrine types in core.** `Resource`, `Column`, `Field` and
  `DataProviderInterface` must not reference Doctrine types. Doctrine lives only
  in the adapter.
- **The Resource API (PRD §9) is a stable contract.** Flag any change to those
  signatures as BC-relevant in your PR description.
- **Mark non-public API with `@internal`.**

## Quality gates (CI must be green)

- PHPUnit tests pass.
- PHPStan is clean at `max`.
- PHP-CS-Fixer reports no violations (Symfony ruleset).
- `composer validate --strict` passes.

## Pull requests

1. Open an issue first for non-trivial changes.
2. Keep PRs focused; reference the relevant PRD requirement ID (e.g. `TBL-02`).
3. Update `CHANGELOG.md` under `[Unreleased]`.
4. Add or update tests and docs.

By contributing, you agree your contributions are licensed under the
[MIT License](LICENSE).
