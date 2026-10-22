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

The checks of the repository's structure read the PSR-4 roots from
`composer.json` and the test suites from `phpunit.xml.dist`. What those files
cannot tell, such as the main plugin file and the view template directories,
`RepositoryLayout` in `tests/php/Architecture/` declares.

## Adding a rule

1. Add an entry to `RuleManifest`: identifier, principle and its anchor in
   `docs/design-principles.md`, and the configuration key.
2. Write the rule in `tests/php/PHPStan/Rule/`, using the identifier and
   `RuleManifest::get_explanation()` as the tip, or write the check as a test
   in `tests/php/Architecture/`.
3. Test a PHPStan rule with PHPStan's `RuleTestCase` against fixtures in
   `tests/php/PHPStan/Fixture/`: one that breaks the rule, with the full
   message asserted, and one for each case it allows. Fixtures that refer to
   each other's classes need one class per file, following PSR-4, because
   PHPStan's test case only finds classes it can autoload.
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

### `architecture.noStaticState`

**Principle:** [5. One composition root builds the object graph](design-principles.md#5-one-composition-root-builds-the-object-graph),
which allows static state only in the plugin factory, and the anti-pattern
table in [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns).

**Checks:** no static properties and no `static` variables in the plugin's
namespace.

**Allows:** static methods and class constants, which hold no state, and the
methods and properties listed in the configuration. Anonymous classes declared
in the plugin's namespace are checked too.

**Configuration:** `noStaticState.allowed` lists methods as `Class::method` and
properties as `Class::$property`. As in PHP, class and method names match
regardless of case, property names only exactly. The scaffold lists
`BasicScaffoldPluginFactory::create`, which keeps the one plugin instance that
WordPress reaches from outside the object graph.

**Fix:** keep the value on an instance, and let the injector share that
instance with `share()` when more than one object needs it. A cache belongs on
the object that computes it.

### `architecture.noWordPressErrorMechanisms`

**Principle:** [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions).

**Checks:** inside the plugin's namespace, no calls to `error_log()`,
`trigger_error()`, `user_error()` or `_doing_it_wrong()`, no `new WP_Error()`,
and no `WP_Error` in a parameter, return or property type, native or in a
docblock, of a method, function, closure or arrow function. A subclass of
`WP_Error` counts as a `WP_Error`.

**Allows:** inspecting what WordPress returns with `is_wp_error()` or
`instanceof WP_Error`, which is how a `WP_Error` becomes an exception where the
call is made. Classes listed as boundary adapters may create and return
`WP_Error` objects, for WordPress APIs that expect one, such as a REST
callback.

**Configuration:** `noWordPressErrorMechanisms.boundaryAdapters` lists the
classes that hand a `WP_Error` to WordPress. The scaffold has none yet.

**Fix:** throw a dedicated exception. The code that hands control back to
WordPress catches it and decides what the user sees.

### `architecture.throwOnlyPluginExceptions`

**Principle:** [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions).

**Checks:** everything the plugin throws implements its marker interface,
`BasicScaffoldException`. The rule checks two places: every exception the
plugin creates with `new`, and every `throw` of anything but a variable.

**Allows:** throwing a variable, such as passing on what a `catch` block
caught. A variable holds either a caught exception or one the plugin created,
and creating one is checked.

**Configuration:** `exceptions.marker` and `exceptions.namespace` name the
marker interface and the namespace the exceptions live in.

**Fix:** add an exception for the failure to `src/Exception/`, shaped as
`architecture.exceptionShape` describes, and throw that.

### `architecture.exceptionShape`

**Principle:** [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions).

**Checks:** every class in the plugin's namespace that implements the marker
interface lives in the exception namespace, extends the SPL exception that
matches its category, and has a named constructor: a public static method that
returns `self`, declared in the class, a parent or a trait. Everything in the
exception namespace implements the marker interface.

**Allows:** interfaces that extend the marker interface, abstract exceptions
without a named constructor, and code listed as not being an exception.

**Configuration:** `exceptions.notExceptions` lists the code in the exception
namespace that is not an exception. The scaffold lists the `Stringify` trait.
Whether exceptions are `final` is checked by `architecture.finalByDefault`.

**Fix:** what the message says: extend `InvalidArgumentException` for invalid
input or configuration and `RuntimeException` for a failure while running, add
a named constructor that states the cause, or move the class.

### `architecture.exceptionsThroughNamedConstructors`

**Principle:** [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions).

**Checks:** `new` of one of the plugin's exceptions only happens inside that
exception's own static methods.

**Allows:** `new self( … )` in a named constructor.

**Configuration:** `exceptions.marker`.

**Fix:** call the named constructor the message lists, or add one that states
the new cause, so the same failure always reads the same.

### `architecture.strictTypes`

**Principle:** the [hard rule](../AGENTS.md#hard-rules) that every PHP class
file declares `strict_types=1`.

**Checks:** the main plugin file and every file below the PSR-4 roots of
`composer.json` that declares a class, interface or trait declare
`strict_types=1`. View templates declare no class, interface, trait or
function, not even conditionally.

**Allows:** view templates without `strict_types`, since they are templates
and not class files.

**Configuration:** `RepositoryLayout` in `tests/php/Architecture/` names the
main file and the view template directories.

**Fix:** add `declare( strict_types=1 );` after the file header, or move a
declaration out of a template into its own class file.

### `architecture.declarationsFollowPsr4`

**Principle:** [1. Runs on PHP and WordPress alone](design-principles.md#1-runs-on-php-and-wordpress-alone),
whose bundled autoloader finds classes the same way Composer does.

**Checks:** every PHP file below a PSR-4 root of `composer.json` declares
exactly one class, interface or trait, and its name matches the file's path
exactly, including case.

**Allows:** anonymous classes, view templates, and the fixtures of the
architecture rules, which break rules on purpose.

**Configuration:** the `autoload` and `autoload-dev` sections of
`composer.json`, and the rule fixture directories in `RepositoryLayout`.

**Fix:** give each class, interface or trait its own file, named after it, in
the directory its namespace points to.

### `architecture.noSideEffectsOnInclude`

**Principle:** [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
only the main plugin file has side effects.

**Checks:** files in the production directories contain nothing at their top
level but `declare` directives, `namespace`, `use` and class, interface or
trait declarations. Function declarations, function calls, `define()`,
includes, the body of a `declare` block and output outside the PHP tags, even
whitespace after a closing `?>`, all count as side effects.

**Allows:** anything inside a class.

**Configuration:** the production directories in `RepositoryLayout`.

**Fix:** move the code into a service method that `register()` hooks in at
the right time, or into the main plugin file.

### `architecture.noRuntimeDependencies`

**Principle:** [1. Runs on PHP and WordPress alone](design-principles.md#1-runs-on-php-and-wordpress-alone).

**Checks:** `composer.json` requires nothing but PHP, and
`composer-dependency-analyser` finds no package used by the code that runs in
production: the main plugin file, `src/` and `views/`. That covers packages in
`require-dev` and packages only installed because another package needs them.

**Allows:** the tests use any package.

**Configuration:** the production paths in `RepositoryLayout`, which
`composer-dependency-analyser.php` in the plugin root hands to the analyser
through `RuntimeDependencyConfiguration`.

**Fix:** move the package to `require-dev` and the code that needs it into the
tests, or bundle what the plugin needs into `src/`.

### `architecture.testsAreDiscovered`

**Principle:** the [hard rule](../AGENTS.md#hard-rules) not to weaken the
gates: a test PHPUnit never runs never fails.

**Checks:** every file ending in `Test.php` below `tests/` lies in a directory
a suite in `phpunit.xml.dist` discovers, and every concrete PHPUnit test case
lives in such a file.

**Allows:** abstract base test cases anywhere.

**Configuration:** the test suites in `phpunit.xml.dist`, and the tests
directory in `RepositoryLayout`.

**Fix:** move the test into a suite directory and name its file after the
class with a `Test.php` suffix, or add a suite for its directory.

### `architecture.dependencyDirection`

**Principle:** [4. SOLID at the top, WordPress at the edges](design-principles.md#4-solid-at-the-top-wordpress-at-the-edges),
whose dependency inversion this keeps, and [5. One composition root builds the object graph](design-principles.md#5-one-composition-root-builds-the-object-graph).

**Checks:** which layer of the plugin a class, interface or trait may depend
on, counting every class it mentions: in its file's imports, in code, in
native types and in any documentation tag, such as `@var`, `@throws` or
`@method`:

| A class in | may depend on |
| --- | --- |
| the exceptions (`src/Exception/`) | other exceptions |
| the infrastructure (`src/Infrastructure/`) | the infrastructure and the exceptions |
| a feature subsystem (any other top-level namespace, such as `src/SampleSubsystem/`) | the infrastructure's interfaces, the exceptions and the subsystems |
| the composition root | everything |
| any other class directly in the root namespace | the infrastructure's interfaces and the exceptions |
| a view template (`views/`) | everything but the tests |

Nothing but the tests depends on the tests, not even the composition root.
Classes from PHP or WordPress are always fine.

**Allows:** a subsystem depending on another subsystem; there is no case yet to
decide otherwise.

**Configuration:** `dependencyDirection.infrastructure` names the
infrastructure's namespace, and `dependencyDirection.compositionRoot` lists the
classes of the composition root: `BasicScaffoldPlugin` and
`BasicScaffoldPluginFactory`. `dependencyDirection.views` lists the
directories of the view templates that run in production. The exceptions' namespace comes from
`exceptions.namespace`, the tests' from `excludedNamespaces`.

**Fix:** depend on the interface the message names and bind the implementation
in the composition root, or receive what you need through the constructor. In
the infrastructure, add an interface the other class implements.

### `architecture.newOnlyWhereAllowed`

**Principle:** [5. One composition root builds the object graph](design-principles.md#5-one-composition-root-builds-the-object-graph).

**Checks:** every `new` in the plugin's namespace, by default. A collaborator,
whether a service of the plugin or a WordPress class such as `WP_Query`, comes
from the injector, not from `new` in the class that uses it. So do
anonymous classes and objects of a class chosen at runtime.

**Allows:**

- anything inside a factory: a class named `…Factory`, a class implementing an
  interface named `…Factory`, or a class implementing one of the configured
  factory interfaces;
- a poka-yoke default, `$this->property = $parameter ?? new Default()` in a
  constructor, when the parameter is nullable and typed to an interface the
  default implements;
- value objects in the configured list, and anything throwable; how exceptions
  are created is up to `architecture.throwOnlyPluginExceptions` and
  `architecture.exceptionsThroughNamedConstructors`.

**Configuration:** `newOnlyWhereAllowed.factories` lists the factory
interfaces: the scaffold lists `Injector` and `Instantiator`.
`newOnlyWhereAllowed.valueObjects` lists the value objects: the scaffold lists
`LazilyInstantiatedService` and PHP's `DateTimeImmutable`, `DateInterval`,
`ArrayIterator` and `SplObjectStorage`.

**Fix:** receive the object through the constructor, typed to its interface,
and bind the implementation in the composition root. Ask a factory for objects
that can only be created later or are needed once per use. Add a class to
`valueObjects` only if it represents a value rather than a collaborator.
