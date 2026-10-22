# Architecture rules

The [design principles](design-principles.md) describe how the scaffold is
built. The rules on this page turn the parts of them that a tool can check into
checks that fail as soon as code breaks them, in the editor, in
`composer lint` and in CI.

They exist for whoever extends the scaffold next, person or coding agent. A
failure does not only say that a rule was broken. It says what to do instead,
and links to the principle and to the rule's entry below.

## Running the checks

```sh
composer lint                  # includes the PHPStan architecture rules
composer test                  # includes the architecture test suite
composer test:architecture     # only the architecture test suite
composer test:phpstan-rules    # only the tests of the PHPStan rules
```

## How the rules are built

Each rule lives at the cheapest level that can see what it checks:

| Level | Mechanism | Where |
| --- | --- | --- |
| Code | PHPStan rules, run with the rest of the static analysis | `tests/php/PHPStan/Rule/` |
| Repository and composition root | PHPUnit tests in the `architecture` suite | `tests/php/Architecture/` |

Every rule has an identifier such as `architecture.finalByDefault` and one
entry in `tests/php/Architecture/RuleManifest.php`. The entry ties the
identifier to the principle it enforces and to its section on this page.

Every failure has the same three parts:

1. **What is wrong, and where**, naming the class or file.
2. **What to do instead.**
3. **Why**: the principle, and the rule's section on this page. PHPStan shows
   this part as the tip under the error.

## When a rule stops you

Change the code so it follows the rule. The message says how.

Never silence an architecture rule with an inline `@phpstan-ignore`, an
`ignoreErrors` entry or a baseline. If the rule is wrong for a real case, open
an issue with `needs: decision` that names the case. Exceptions the maintainer
accepts become configuration under `parameters.architecture` in
`phpstan.neon.dist`, where they stay visible and reviewed.

## Configuration

The rules read the plugin's namespace and their allowances from
`parameters.architecture` in `phpstan.neon.dist`. A plugin generated from the
scaffold renames the namespaces there together with its own namespace, and
the rules follow.

| Setting | Meaning |
| --- | --- |
| `namespace` | The plugin's root namespace. Rules only look at code inside it. |
| `excludedNamespaces` | Parts of that namespace that are not plugin code, such as the tests. |

Settings that belong to one rule are listed in its section.

## Adding a rule

1. Add an entry to `RuleManifest`: identifier, principle and its anchor in
   `docs/design-principles.md`, and the configuration key.
2. Write the rule in `tests/php/PHPStan/Rule/`, using the identifier and
   `RuleManifest::get_explanation()` as the tip, or write the check as a test
   in `tests/php/Architecture/`.
3. Test a PHPStan rule with PHPStan's `RuleTestCase` against fixtures in
   `tests/php/PHPStan/Fixture/`: one that breaks the rule, with the full
   message asserted, and one for each case it allows.
4. Register it in `tests/php/PHPStan/rules.neon`, and put its settings under
   `parameters.architecture` in `phpstan.neon.dist`.
5. Add its section below. `RuleManifestTest` fails until it exists.

The tree must pass a new rule without a baseline. Fix what it finds, or file
the findings as their own issues and land the rule after them.

PHPStan rules only use the parts of PHPStan's API that its versions 1 and 2
share, because `composer.json` allows both.

## Rules

### `architecture.finalByDefault`

**Principle:** [6. Final by default](design-principles.md#6-final-by-default).

**Checks:** every concrete class in the plugin's namespace is `final`.

**Allows:** abstract classes, interfaces, traits and anonymous classes, and the
classes listed as designed base classes. A `@final` tag in a docblock does not
count: the class can still be extended.

**Configuration:** `finalByDefault.baseClasses` lists the classes designed to be
extended. The scaffold lists `SimpleView`, the base of `TemplatedView`.

**Fix:** declare the class `final`, and vary its behaviour through interfaces
and injected collaborators. Add it to `baseClasses` only when it is designed
for extension from the start.
