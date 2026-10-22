# AGENTS.md

Rules for any coding agent working in this repository.

## What this is

`mwpd/basic-scaffold` is a template for WordPress plugins, the companion code
to the *Modern WordPress Plugin Development* book. People generate a plugin
from it and replace the sample code with their own. So the scaffold's
infrastructure is effectively a public API, and its sample code is teaching
material.

| Path | What it is |
| --- | --- |
| `basic-scaffold.php` | Bootstrap: plugin header, autoloading, kicks off the composition root. |
| `src/BasicScaffoldPlugin.php` | Composition root: services, injector bindings, prefixes. |
| `src/Infrastructure/` | What generated plugins build on: service container, injector, views, autoloader. |
| `src/Exception/` | The scaffold's exception types. |
| `src/SampleSubsystem/`, `views/` | Example services and templates that a generated plugin replaces. |
| `tests/php/Unit`, `tests/php/Integration` | PHPUnit suites. Both use Brain Monkey; neither needs a WordPress install. |
| `tests/php/Fixture/` | Test doubles and view fixtures. |

## Commands

```sh
composer install
composer test               # unit + integration
composer lint               # phpcs, phpstan (level max), psalm (level 2)
composer phpcs:fix          # auto-fix coding standards
vendor/bin/rector --dry-run # rule sets up to PHP 7.4, code quality, style
```

Psalm 5 runs on PHP 8.3 at most, and `composer.lock` was resolved for PHP 8.
CI (`.github/workflows/ci.yml`) is the reference environment: lint on 8.3,
tests on 7.4 and 8.3.

## Design principles

Every technical decision is measured against
[docs/design-principles.md](docs/design-principles.md). Read it before
choosing between approaches. In short:

1. **Runs on PHP and WordPress alone.** Composer is a development tool, never
   a runtime requirement; the fallback autoloader keeps the plugin working
   without `vendor/`, and nothing needs building before it runs.
2. **Self-contained infrastructure, replaceable by design.** Simple in-house
   implementations (injector, container, views) behind interfaces, rather than
   third-party packages, so a stronger implementation can be swapped in later.
3. **SOLID at the top, WordPress at the edges.** The high-level design
   (infrastructure, composition root, service boundaries, interfaces) strictly
   follows SOLID; implementation details may use WordPress and its plugin API
   directly.
4. **Show the alternative to WordPress anti-patterns.** No hooks in
   constructors, no singletons, no `new` or globals for collaborators, no side
   effects on include. The doc lists each with the scaffold's alternative.
5. **One plugin, to arbitrary complexity.** No cross-plugin interoperability
   or synchronization mechanisms; those belong in a separate mechanism.
6. **Types name concepts, not just data.** Distinct concepts get distinct
   types, even when they share a data structure.
7. **Never trust what WordPress hands over.** Sanitize, validate and normalize
   at the boundary into internal types; internal code then relies on those.
8. **Errors are exceptions.** No `WP_Error`, falsy returns or log writes for
   internal errors; convert at the WordPress boundary. Custom exceptions extend
   the matching SPL exception, implement `BasicScaffoldException`, and are
   built through named constructors.
9. **One composition root builds the object graph.** Everything in the
   application flow comes from it via constructor injection, deferred only by
   lazy instantiation or on-demand creation through the injector. `new` is
   only for value objects (and exceptions), inside factories, and for
   poka-yoke defaults.
10. **Defaults out of the box (poka-yoke).** Optional collaborators fall back
    to a built-in implementation, so the default needs no wiring and a custom
    one is just passed in.

A change that contradicts a principle needs `needs: decision` and a maintainer
ruling on its issue before code.

## Hard rules

- **`basic-scaffold.php` is the only file with side effects.** Everything under
  `src/` is autoloadable declarations: no code that runs on include.
- **PHP 7.4 is the floor.** No syntax or functions newer than 7.4 in `src/`,
  `tests/` or `views/`. `phpcs` enforces it through PHPCompatibility.
- **Every PHP class file declares `strict_types=1`**: the bootstrap, `src/`
  and `tests/php`. View templates (`views/`, `tests/php/Fixture/views/`) do not.
- **No runtime Composer dependencies** (principle 1). `require` holds nothing
  but PHP; class loading must work both with and without `vendor/`.
- **Infrastructure changes are API changes.** Anything a generated plugin
  extends or calls in `src/Infrastructure/` needs the `breaking` label and a
  ruling on the issue before code if it changes shape.
- **Do not weaken the gates to get green.** No lowered PHPStan/Psalm levels,
  new baselines, broad ignores, or deleted tests. Fix the code or say why on
  the issue.
- **Verify technical claims against the source**, such as the installed package,
  upstream docs or the registry, before building on them. Do not carry a version
  number or an API shape from memory.

## Working through GitHub

**Load the [`github` skill](.agents/skills/github/SKILL.md) before tracker
work.** It implements [docs/process/github.md](docs/process/github.md):
labels, readiness, the lifecycle, and filing found work.

- `triage-issues` prepares issues for unattended work; `work-issue` takes one
  pickable issue through to a merged PR. Both live in `.agents/skills/`.
- Before writing code against an issue, read it and its epic, and **verify
  that its `file:line` citations still point at what it claims**.
- **Work found mid-session gets filed, not fixed and not forgotten.** An issue
  with no acceptance criteria is a note, so it does not get `agent-ready`.
- Commits and PR titles are plain imperative sentences, matching the existing
  log ("Add dependency resolution"). Branches are `<type>/<issue>-<slug>`.
