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
| `src/SampleSubsystem/`, `views/` | Example services and templates that a generated plugin replaces, apart from `views/query-monitor-registration.php`, the template of the optional Query Monitor panel in `src/Infrastructure/QueryMonitor/`. |
| `tests/php/Unit`, `tests/php/Integration` | PHPUnit suites. Both use Brain Monkey; neither needs a WordPress install. |
| `tests/php/Fixture/` | Test doubles and view fixtures. |
| `tests/php/Architecture/`, `tests/php/PHPStan/` | Architecture rules: the `architecture` suite, PHPStan rules, their tests and fixtures. |

## Commands

```sh
composer install
composer test               # unit + integration + architecture
composer lint               # phpcs, phpstan (level max), psalm (level 2), PHPStan rule tests
composer phpcs:fix          # auto-fix coding standards
vendor/bin/rector --dry-run # rule sets up to PHP 7.4, code quality, style
```

The architecture rules are part of both: PHPStan rules in `composer lint`, the
`architecture` test suite in `composer test`. Every failure says what to do
instead; [docs/architecture-rules.md](docs/architecture-rules.md) lists the
rules and how to add one.

Psalm 5 runs on PHP 8.3 at most, and `composer.lock` was resolved for PHP 8.
CI (`.github/workflows/ci.yml`) is the reference environment: lint on 8.3,
tests on 7.4 and 8.3.

## Design principles

Every technical decision is measured against
[docs/design-principles.md](docs/design-principles.md). Read it before
choosing between approaches.

**Look up the need before the workaround.** When you are about to reach for
something the principles rule out (a service locator, a singleton, a global,
an `is_admin()` check around hooks), or when an architecture rule fails, find
what you were trying to do in [docs/patterns.md](docs/patterns.md). Each
pattern shows the scaffold's way, with code that passes the checks.

**The core idea: complexity grows linearly.** Every concern is its own
service, and each service decides in isolation where and when it acts in the
WordPress lifecycle, by implementing small capability interfaces
(`Conditional`, `Delayed`, `HasDependencies`, `Registerable`, `Activateable`,
`Deactivateable`, and the site- and network-scoped lifecycle interfaces such as
`SiteActivateable` and `SiteUninstallable`). The infrastructure works out when
to trigger what. New
logic is one more service, never one more condition threaded through the
others. A lifecycle point no interface covers gets a new interface, not a
conditional inside a service.

The principles, in short:

1. **Runs on PHP and WordPress alone.** Composer is a development tool, never
   a runtime requirement; the fallback autoloader keeps the plugin working
   without `vendor/`.
2. **Self-contained infrastructure, replaceable by design.** Simple in-house
   implementations behind interfaces, not third-party packages.
3. **Defaults out of the box (poka-yoke).** Optional collaborators fall back
   to a built-in implementation; a custom one is simply passed in.
4. **SOLID at the top, WordPress at the edges.** Strict SOLID for the
   high-level design; implementation details may use WordPress directly.
5. **One composition root builds the object graph.** Deferred only by lazy or
   on-demand instantiation through the injector. `new` is only for value
   objects and exceptions, inside factories, and for poka-yoke defaults.
6. **Final by default.** Only classes designed as a base are open.
7. **Show the alternative to WordPress anti-patterns.** No hooks in
   constructors, no singletons, no `new` or globals for collaborators, no side
   effects on include.
8. **Types name concepts, not just data.**
9. **Never trust what WordPress hands over.** Normalize at the boundary into
   internal types; escape output for the context it lands in.
10. **Errors are exceptions.** SPL-based, one marker interface, named
    constructors. They never reach the end user: loud with debug mode on,
    graceful degradation with it off.
11. **Lifecycle steps are idempotent.**
12. **Extension points run at a hook, never at include time.**
13. **One plugin, to arbitrary complexity.** No cross-plugin coordination;
    everything public carries the plugin's prefix.
14. **The code teaches.** Comments explain why; samples must be correct and
    tested.

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
