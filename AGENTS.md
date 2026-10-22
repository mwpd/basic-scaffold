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

## Hard rules

- **`basic-scaffold.php` is the only file with side effects.** Everything under
  `src/` is autoloadable declarations: no code that runs on include.
- **PHP 7.4 is the floor.** No syntax or functions newer than 7.4 in `src/`,
  `tests/` or `views/`. `phpcs` enforces it through PHPCompatibility.
- **Every PHP class file declares `strict_types=1`**: the bootstrap, `src/`
  and `tests/php`. View templates (`views/`, `tests/php/Fixture/views/`) do not.
- **No runtime Composer dependencies** without a maintainer ruling. The bundled
  fallback autoloader exists so the plugin works without `composer install`.
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
