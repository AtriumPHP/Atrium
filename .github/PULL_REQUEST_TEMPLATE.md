## Summary

Briefly describe what this PR changes and why.

## Related

- Closes #
- Requirement ID(s) (e.g. `TBL-03`, `FRM-05`):

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor / internal change
- [ ] Documentation
- [ ] Breaking change

## Public API impact

- [ ] This PR does **not** change the public Resource API (`docs/PRDs/PRD.md` §9).
- [ ] This PR **does** change a public/stable signature (described below and
      noted in `CHANGELOG.md`).

## Checklist

- [ ] `composer test` passes
- [ ] `composer phpstan` is clean at max
- [ ] `composer cs` is clean
- [ ] `declare(strict_types=1)` present; `@internal` where appropriate
- [ ] No Doctrine types leaked into core; no sideways package deps introduced
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] Docs/README updated if the developer-facing surface changed
