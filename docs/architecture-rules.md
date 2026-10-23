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
identifier to the principle it enforces, to its section on this page and,
where one applies, to the [pattern](patterns.md) that meets the need the
rejected code was after.

Every failure has the same three parts:

1. **What is wrong, and where**, naming the class or file.
2. **What to do instead.**
3. **Why, how and what exactly**: the principle, the pattern when there is
   one, and the rule's section on this page. PHPStan shows this part as the
   tip under the error.

## When a rule stops you

Change the code so it follows the rule. The message says how, and
[docs/patterns.md](patterns.md) shows the scaffold's way of meeting the need
the code was after, with examples that pass the checks.

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
   `docs/design-principles.md`, the configuration key, what checks it
   (`checked_by`: the PHPStan rule classes, or the test methods of the
   architecture suite), and fixed phrases of its messages (`signatures`),
   which let the suppression check recognize ignores aimed at it. When a
   section of `docs/patterns.md` shows how to meet the need the rule
   protects, name its anchor as `pattern`, and list the rule under that
   section's **Rules**; add a section if the need is new and recurs.
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
5. Add its section below, and link each pattern that lists the rule under
   its **Fix**. `RuleManifestTest` fails until the section exists, and
   `RuleRegistrationTest` until the rule and its patterns lead to each other.

The tree must pass a new rule without a baseline. Fix what it finds, or file
the findings as their own issues and land the rule after them.

PHPStan rules only use the parts of PHPStan's API that its versions 1 and 2
share, because `composer.json` allows both.

## What each principle is checked by

Not everything a principle asks for can be checked mechanically. This table
says which rules enforce each principle, and what stays with review.

| Principle | Checked by | Reviewed by hand |
| --- | --- | --- |
| [The core idea: complexity that grows linearly](design-principles.md#the-core-idea-complexity-that-grows-linearly) | `hooksInLifecycleMethods`, `queryContextNeedsDelay`, `capabilitiesOnServices`, `servicesDeclared`, `compositionRootContracts` | Each service deciding its own lifecycle through capability interfaces |
| [1. Runs on PHP and WordPress alone](design-principles.md#1-runs-on-php-and-wordpress-alone) | `noRuntimeDependencies`, `declarationsFollowPsr4`, `explicitNullableParameters` | Loading without `vendor/` (#33) |
| [2. Self-contained infrastructure](design-principles.md#2-self-contained-infrastructure-replaceable-by-design) | `dependencyDirection` (subsystems depend on interfaces) | Keeping the simple implementations simple |
| [3. Defaults out of the box](design-principles.md#3-defaults-out-of-the-box-poka-yoke) | `newOnlyWhereAllowed` (the shape of a poka-yoke default) | Whether a collaborator has an obvious default |
| [4. SOLID at the top](design-principles.md#4-solid-at-the-top-wordpress-at-the-edges) | `dependencyDirection` | Single responsibility, open/closed and Liskov substitution |
| [5. One composition root](design-principles.md#5-one-composition-root-builds-the-object-graph) | `newOnlyWhereAllowed`, `noStaticState`, `pluginFactoryOnlyAtEdges`, `dependencyDirection`, `capabilitiesOnServices`, `servicesDeclared`, `compositionRootContracts` | |
| [6. Final by default](design-principles.md#6-final-by-default) | `finalByDefault` | Whether a listed base class is designed for extension |
| [7. WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns) | `noHooksInConstructor`, `hooksInLifecycleMethods`, `noContextChecksInRegister`, `queryContextNeedsDelay`, `noSideEffectsOnInclude`, `noStaticState`, `pluginFactoryOnlyAtEdges`, `newOnlyWhereAllowed`, `noGlobalState`, `outputOnlyThroughViews` | God classes |
| [8. Types name concepts](design-principles.md#8-types-name-concepts-not-just-data) | PHPStan at level `max` and Psalm at level 2, for consistency | Whether a value deserves a type of its own |
| [9. Never trust what WordPress hands over](design-principles.md#9-never-trust-what-wordpress-hands-over) | `noGlobalState` (request input only in adapters), `rawIsEscaped`, PHPCS (WordPress Coding Standards) for escaping output | Validating input at the boundary |
| [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions) | `noWordPressErrorMechanisms`, `noFalseOnFailure`, `throwOnlyPluginExceptions`, `exceptionShape`, `exceptionsThroughNamedConstructors`, `exceptionsKeepCause` | Graceful degradation at the boundary (#27, #30) |
| [11. Lifecycle steps are idempotent](design-principles.md#11-lifecycle-steps-are-idempotent) | `compositionRootContracts` | Plugin registration orchestration (#26); effects outside Brain Monkey |
| [12. Extension points at a hook](design-principles.md#12-extension-points-run-at-a-hook-never-at-include-time) | `noSideEffectsOnInclude`, `noHooksInConstructor` | Filters applied too early in the lifecycle |
| [13. One plugin, to arbitrary complexity](design-principles.md#13-one-plugin-to-arbitrary-complexity) | `prefixedNames` | Prefixes of service identifiers and view override paths (#29) |
| [14. The code teaches](design-principles.md#14-the-code-teaches) | `testsAreDiscovered`, so the samples' tests run; `citationsResolve` | Whether comments explain why and cited lines still support the prose |
| [Hard rules](../AGENTS.md#hard-rules) | `strictTypes`, `noSuppression`, `testsAreDiscovered`, `gatesAtStrength` | |

## Mistakes the checks catch

Each of these mistakes fails `composer lint` or `composer test` with a message
that names the fix. The fixtures and tests listed prove it.

| Mistake | Fails with | Proven by |
| --- | --- | --- |
| A hook added in a constructor | `noHooksInConstructor` | `tests/php/PHPStan/Fixture/Hooks/Feature/BadService.php` |
| `new` of a service inside another service | `newOnlyWhereAllowed` | `tests/php/PHPStan/Fixture/Creation/Consumer.php` |
| A class that is not `final` | `finalByDefault` | `tests/php/PHPStan/Fixture/FinalByDefault/classes.php` |
| A bare SPL exception thrown | `throwOnlyPluginExceptions` | `tests/php/PHPStan/Fixture/Errors/Service.php` |
| A public WordPress name without the plugin's prefix, or an overlong post type/taxonomy name | `prefixedNames` | `tests/php/PHPStan/Fixture/Names/Service.php`, `Registrations.php`, `NameLengths.php` |
| A test file outside a test suite | `testsAreDiscovered` | `RepositoryStructureTest::test_it_reports_tests_phpunit_never_runs` |
| A service calling the plugin factory as a static service locator | `pluginFactoryOnlyAtEdges` | `tests/php/PHPStan/Fixture/PluginFactoryOnlyAtEdges/Service.php` |
| A documentation citation naming a missing file, an out-of-bounds line or a reversed range | `citationsResolve` | `RepositoryStructureTest::test_it_reports_broken_documentation_citations_with_full_messages` |
| A service pulling a collaborator or request input from globals | `noGlobalState` | `tests/php/PHPStan/Fixture/NoGlobalState/Service.php` |
| HTML or a string printed directly by a service | `outputOnlyThroughViews` | `tests/php/PHPStan/Fixture/OutputOnlyThroughViews/Service.php` |
| A raw view value printed without escaping | `rawIsEscaped` | `tests/php/PHPStan/Fixture/RawEscaping/views/unescaped.php` |
| A sample service depending on a concrete infrastructure class | `dependencyDirection` | `tests/php/PHPStan/Fixture/Layers/Feature/Service.php` |
| A lifecycle capability on a class that is not a service | `capabilitiesOnServices` | `tests/php/PHPStan/Fixture/Capabilities/RegisterableOnly.php` |
| A delayed service with nothing to register | `capabilitiesOnServices` | `tests/php/PHPStan/Fixture/Capabilities/DelayedService.php` |
| A subsystem service missing from the composition root | `servicesDeclared` | `ServicesDeclaredTest::test_it_reports_an_undeclared_subsystem_service_with_the_fix` |
| A service the composition root cannot build | `compositionRootContracts` | `tests/php/Fixture/CompositionRoot/BrokenPlugin.php` |

## Rules

### `architecture.citationsResolve`

**Principle:** [14. The code teaches](design-principles.md#14-the-code-teaches).

**Checks:** repository-relative file citations in inline code spans in
`AGENTS.md`, `README.md` and every Markdown document below `docs/`. A citation
names a path with a recognizable file extension followed by a colon and a
line number or line range. The file must exist, line numbers start at 1,
neither endpoint may exceed the file's line count, and ranges cannot run
backwards. Failure messages name the document, citation and problem.

**Allows:** ordinary prose, fenced code blocks, URLs, host-and-port pairs,
static references, absolute paths and parent-relative paths. Single- and
multiple-backtick inline spans, including multiline spans, are checked.
This checks file and line existence; whether the cited lines still support
the prose stays with review. Citations in issue bodies are outside its scope.

**Configuration:** `RepositoryLayout::DOCUMENTATION` lists individual Markdown
files and directories to search recursively. The check uses repository paths
without depending on the plugin's namespace or prefixes. Recognized file
extensions are `php`, `md`, `txt`, `json`, `lock`, `neon`, `yml`, `yaml`, `xml`,
`dist`, `ini`, `sh`, `js`, `ts`, `css`, `html` and `svg`.

**Fix:** correct the path or line numbers to cite existing lines in the
repository. Check that those lines also support the surrounding explanation.

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

**Pattern:** [I need to start simple and add implementations later](patterns.md#i-need-to-start-simple-and-add-implementations-later).

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

**Patterns:** [I need a singleton](patterns.md#i-need-a-singleton), [I need a service locator](patterns.md#i-need-a-service-locator), [I need to reach the plugin from a static WordPress callback](patterns.md#i-need-to-reach-the-plugin-from-a-static-wordpress-callback).

### `architecture.pluginFactoryOnlyAtEdges`

**Principles:** [5. One composition root builds the object graph](design-principles.md#5-one-composition-root-builds-the-object-graph)
and [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns).

**Checks:** no direct static call to a configured plugin factory from the
plugin's namespace or production views. Inside the object graph, the factory
would be a static service locator. Calls through `self`, `static` and `parent`
are resolved to their class too.

**Allows:** the main plugin file declared by `RepositoryLayout::BOOTSTRAP`,
and calls directly inside a listed bridge method. Other methods of the same
class and closures inside a bridge are still checked. Tests and unrelated
namespaces are outside the rule's scope; unrelated static calls are untouched.
Calls through a class expression chosen at runtime are not checked.

**Configuration:** `pluginFactoryOnlyAtEdges.factories` lists factory classes;
the scaffold lists `MWPD\BasicScaffold\BasicScaffoldPluginFactory`.
`pluginFactoryOnlyAtEdges.bridges` lists reviewed methods as `Class::method`
and is empty by default. Class and method names match without regard to case
or a leading backslash. `dependencyDirection.views` supplies the production
view directories, as for `noGlobalState`.

**Fix:** receive what is needed through the constructor. For on-demand
creation, receive a factory or the injector. A static callable that WordPress
invokes belongs in a listed bridge: get the graph through the plugin factory
and delegate immediately to instance services, nothing else. The bridge holds
no state or feature logic; reviewing that contract remains a human check.
See [I need to reach the plugin from a static WordPress callback](patterns.md#i-need-to-reach-the-plugin-from-a-static-wordpress-callback).

### `architecture.noGlobalState`

**Principles:** [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
and [9. Never trust what WordPress hands over](design-principles.md#9-never-trust-what-wordpress-hands-over).

**Checks:** no `global` statements or `$GLOBALS` access in the plugin's
namespace (excluding `excludedNamespaces`) or production view directories.
This includes the namespaced bootstrap, file scope, functions, closures and
anonymous classes. The request superglobals `$_GET`, `$_POST`, `$_REQUEST`,
`$_COOKIE`, `$_SERVER`, `$_FILES` and `$_ENV` are only allowed inside listed
input adapter classes. Reads and writes both count as access.

**Allows:** constructor-injected values and calls to WordPress functions such
as `get_post()`, even when those functions read globals internally. Other
superglobals, such as `$_SESSION`, are outside this rule's scope. Listing an
adapter never permits `global` or `$GLOBALS`.

**Configuration:** `noGlobalState.inputAdapters` lists classes that sanitize
and normalize request input into internal types; the scaffold needs none.
Class names match without regard to case or a leading backslash; subclasses
need their own entry. Closures inside a listed class may read input; functions
and closures in templates without a class are not adapters.
`dependencyDirection.views` supplies the production view directories.

**Fix:** receive the value through the constructor. Configure a delegation in
the composition root for collaborators such as `$wpdb` or `WP_Query`, as for
`WP_Post` in `src/BasicScaffoldPlugin.php`. Read request input in a listed
adapter and pass its normalized internal type to the service. Sanitization
itself remains with WordPress Coding Standards' `ValidatedSanitizedInput`
sniff and review. See [I need WordPress state, such as the current post](patterns.md#i-need-wordpress-state-such-as-the-current-post).

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

**Pattern:** [I need to report a failure](patterns.md#i-need-to-report-a-failure).

### `architecture.noFalseOnFailure`

**Principle:** [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions).

**Checks:** inside the plugin's namespace, no method, function, closure or arrow
function declares a return type that unions literal `false` with another type,
such as `string|false`. Native unions and `@return`, `@phpstan-return` and
`@psalm-return` tags are checked.

**Allows:** plain `bool`, `false` alone, nullable returns and inferred return
types. A union nested inside a returned value, such as `array<string|false>`,
is allowed. Whether `false` or `null` signals a failure in other cases stays
with review. The rule does not inspect what WordPress functions return.

**Configuration:** none beyond the plugin's namespace.

**Fix:** return the value and throw a dedicated exception on failure. Turn a
`false` returned by a WordPress function into an exception where the call is
made.

**Pattern:** [I need to report a failure](patterns.md#i-need-to-report-a-failure).

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

**Pattern:** [I need to report a failure](patterns.md#i-need-to-report-a-failure).

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

**Pattern:** [I need to report a failure](patterns.md#i-need-to-report-a-failure).

### `architecture.exceptionsThroughNamedConstructors`

**Principle:** [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions).

**Checks:** `new` of one of the plugin's exceptions only happens inside that
exception's own static methods.

**Allows:** `new self( … )` in a named constructor.

**Configuration:** `exceptions.marker`.

**Fix:** call the named constructor the message lists, or add one that states
the new cause, so the same failure always reads the same.

**Pattern:** [I need to report a failure](patterns.md#i-need-to-report-a-failure).

### `architecture.exceptionsKeepCause`

**Principle:** [10. Errors are exceptions](design-principles.md#10-errors-are-exceptions).

**Checks:** inside a catch in the plugin's namespace, a throw of a new
exception through `new` or a static named constructor passes the caught
variable directly as an argument. An empty catch, including one containing
only comments, also fails. Nested catches are checked against their own
caught variable; functions, closures and classes declared inside a catch are
separate scopes.

**Allows:** bare rethrows (`throw $caught;`), translations that pass the caught
variable, and empty catches in listed methods. The check does not follow
variable aliases or inspect how a constructor uses its arguments.

**Configuration:** `exceptionsKeepCause.emptyCatches` lists reviewed recoveries
as `Class::method`, with a reason beside each entry. Class and method names
match regardless of case and allow a leading backslash. An allowance does
not extend to closures inside the method and does not permit losing a cause
when throwing. The scaffold needs no empty catches.

**Fix:** pass the caught exception to the constructor or named constructor as
its previous cause, or rethrow it. Handle the failure in an empty catch; list
the method only when doing nothing is an intentional, reviewed recovery.
See [I need to report a failure](patterns.md#i-need-to-report-a-failure).

### `architecture.outputOnlyThroughViews`

**Principle:** [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
HTML belongs in views, rather than in business logic.

**Checks:** in the plugin's namespace, every expression output by `echo`,
`print`, `printf()` or `vprintf()` is a direct method call on an object
PHPStan knows is a `Renderable` or `View`. Each argument of a multi-argument
`echo` or formatting call is checked; a rendered view never exempts its
siblings. Inline HTML in namespaced PHP files also fails. String literals,
string variables (even a saved rendering result), concatenations and calls
on unrelated objects with a `render()` method fail.

**Allows:** calls such as `echo $view->render()`,
`echo $factory->create( 'views/notice' )->render()` and
`echo $view->render_partial( 'views/partial' )`. Production templates,
code outside the plugin's namespace, `excludedNamespaces` and explicitly
listed classes are exempt. WordPress output functions such as
`wp_send_json()` are outside this rule's scope.

**Configuration:** `outputOnlyThroughViews.allowed` lists named classes
allowed to output directly; the scaffold needs none. Names match without
regard to case or a leading backslash; subclasses need their own entry.
The rendering interfaces' namespace comes from
`dependencyDirection.infrastructure`, and production template directories
from `dependencyDirection.views`.

**Fix:** render a view from an injected `ViewFactory` and echo its result.
Put markup and formatting in the template. Escaping inside templates is
checked separately by `architecture.rawIsEscaped` and PHPCS. The sample's
PHPCS suppression is still needed because PHPCS does not recognize the
rendering method as automatically escaped.

**Pattern:** [I need to print output](patterns.md#i-need-to-print-output).

### `architecture.rawIsEscaped`

**Principle:** [9. Never trust what WordPress hands over](design-principles.md#9-never-trust-what-wordpress-hands-over).

**Checks:** expressions printed with `echo`, `<?=` or `print` in production
view directories escape every `$this->raw( … )` occurrence and every use of a
variable directly assigned from that call, including its properties and array
items. Each diagnostic names the template file, output line and escaping
function family.

**Allows:** property reads such as `$this->plugin`, which the view escapes
already, and raw values enclosed in a configured escaping function's argument.
Concatenation and nesting inside that argument pass, for example
`esc_html( 'Title: ' . strtoupper( $this->raw( 'title' ) ) )`. Escaping one
concatenation fragment does not escape its siblings. Unlisted functions such
as `strtoupper()` alone do not count as escaping.

The check follows only direct assignments from `raw()` in the template, not
copies, helper return values, closure captures or interprocedural data flow.
Once directly assigned from `raw()`, a variable needs escaping for the remainder of the
template; the check conservatively does not prove that later assignments or
branches make it safe. Nested function and class bodies are checked in their
own scope, so their local assignments do not affect the enclosing template.

**Configuration:** `dependencyDirection.views` supplies the same production
view directories as the dependency rule. `rawIsEscaped.escapingFunctions`
lists the accepted functions, matched without regard to case or a leading
backslash. Defaults are `esc_html`, `esc_attr`, `esc_url`, `esc_js`,
`esc_textarea`, `esc_xml`, `wp_kses`, `wp_kses_post`, `wp_kses_data`, `absint`
and `intval`.

**Fix:** wrap each raw value, or the expression containing it, in the listed
function appropriate to its output context. The check enforces explicit
escaping; choosing the right context still needs review. See
[I need to print output](patterns.md#i-need-to-print-output).

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

### `architecture.explicitNullableParameters`

**Principle:** [1. Runs on PHP and WordPress alone](design-principles.md#1-runs-on-php-and-wordpress-alone).

**Checks:** typed parameters with a `null` default in production class
directories declare their type explicitly nullable. The architecture test
reads the syntax tree, so it also catches PHP 8.4 deprecations on PHP 7.4.

**Allows:** untyped parameters and explicitly nullable types.

**Configuration:** the production directories in `RepositoryLayout`.

**Fix:** add `?` before the parameter type, keeping its `null` default.

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

**Pattern:** [I need to run code when the plugin file loads](patterns.md#i-need-to-run-code-when-the-plugin-file-loads).

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

### `architecture.gatesAtStrength`

**Principle:** the [hard rule](../AGENTS.md#hard-rules) not to weaken the gates.

**Checks:** PHPStan stays at `level: max` without baseline includes; Psalm's
`errorLevel` is at most 2 and has no `errorBaseline` attribute; PHPCS references
both `WordPress` and `PHPCompatibility` with `testVersion` set to `7.4-`;
`phpunit.xml.dist` declares the `unit`, `integration`, `architecture` and
`phpstan-rules` suites. Missing, unreadable or malformed configurations fail
with guidance to restore them. Local PHPStan includes are checked too, and an
include whose filename contains `baseline` is rejected even if it is missing.
Included files containing PHPStan's generated counted, path-specific ignores
are recognized as baselines even under another filename.

**Allows:** stronger Psalm levels, additional PHPUnit suites and ordinary
PHPStan extension includes. Installed packages' configurations are not checked.
Existing PHPStan `ignoreErrors` entries and Psalm `issueHandlers` are outside
this rule's scope, as are inline suppressions and which paths each tool checks.

**Configuration:** the expected levels, standards, compatibility version and
suite names are constants at the top of `GatesAtStrengthChecks` in
`tests/php/Architecture/`. A deliberate change to a value is a one-line diff
there for review.

**Fix:** fix the code instead of weakening the gate; restore the required
configuration. A deliberate change to the policy needs a maintainer ruling
on an issue with `needs: decision` before changing the expectation.

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

**Patterns:** [I need to create objects on demand](patterns.md#i-need-to-create-objects-on-demand), [I need to choose an implementation at runtime](patterns.md#i-need-to-choose-an-implementation-at-runtime), [I need to add a feature](patterns.md#i-need-to-add-a-feature).

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
  default implements; the default may be an anonymous class;
- value objects in the configured list, and anything throwable; how exceptions
  are created is up to `architecture.throwOnlyPluginExceptions` and
  `architecture.exceptionsThroughNamedConstructors`.

A class chosen at runtime, `new $class()`, is allowed when PHPStan can prove it
is a value object or throwable.

**Configuration:** `newOnlyWhereAllowed.factories` lists the factory
interfaces: the scaffold lists `Injector` and `Instantiator`.
`newOnlyWhereAllowed.valueObjects` lists the value objects: the scaffold lists
`LazilyInstantiatedService`, `ViewLocations` and PHP's `DateTimeImmutable`,
`DateInterval`, `ArrayIterator` and `SplObjectStorage`.

**Fix:** receive the object through the constructor, typed to its interface,
and bind the implementation in the composition root. Ask a factory for objects
that can only be created later or are needed once per use. Add a class to
`valueObjects` only if it represents a value rather than a collaborator.

**Patterns:** [I need to create objects on demand](patterns.md#i-need-to-create-objects-on-demand), [I need a default collaborator that can be replaced](patterns.md#i-need-a-default-collaborator-that-can-be-replaced), [I need WordPress state, such as the current post](patterns.md#i-need-wordpress-state-such-as-the-current-post), [I need to choose an implementation at runtime](patterns.md#i-need-to-choose-an-implementation-at-runtime).

### `architecture.noHooksInConstructor`

**Principle:** [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
creating an object never changes global state.

**Checks:** no constructor in the plugin's namespace calls `add_action()`,
`add_filter()`, `remove_action()`, `remove_filter()`, `do_action()`,
`apply_filters()`, `register_activation_hook()` or
`register_deactivation_hook()`.

**Allows:** a closure the constructor creates, which runs later.

**Configuration:** none beyond the plugin's namespace.

**Fix:** keep the constructor to storing dependencies, and add the hooks in
`register()`, which the plugin calls at the right time.

**Pattern:** [I need to add hooks](patterns.md#i-need-to-add-hooks).

### `architecture.hooksInLifecycleMethods`

**Principle:** [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns),
and [the core idea](design-principles.md#the-core-idea-complexity-that-grows-linearly)
of a service that decides once where it acts.

**Checks:** in a feature subsystem, `add_action()`, `add_filter()`,
`remove_action()` and `remove_filter()` are only called directly in
`register()`, `activate()` or `deactivate()` of a class implementing
`Registerable`, `Activateable` or `Deactivateable`.

**Allows:** the infrastructure, which orchestrates the lifecycle, and the
composition root. A closure, even one created in `register()`, is a callback
and does not count as the lifecycle method. Calls made directly in a
constructor are reported by `architecture.noHooksInConstructor` instead.

**Configuration:** `hooks.lifecycleMethods` maps each lifecycle method to its
interface. The infrastructure's namespace and the composition root come from
`dependencyDirection`.

**Fix:** add the hook in `register()`. A hook that has to wait for a later
action belongs to a service that implements `Delayed`.

**Patterns:** [I need to add hooks](patterns.md#i-need-to-add-hooks), [I need to hook in later](patterns.md#i-need-to-hook-in-later).

### `architecture.noContextChecksInRegister`

**Principle:** [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
no `is_admin()` checks wrapped around hook calls, or hooking to an early
action just to wait for a later one.

**Checks:** `register()` does not call a request-context or hook-timing
function directly.

**Allows:** request-context and hook-timing functions everywhere else,
including in the closures and arrow functions `register()` adds as callbacks,
which run later. Infrastructure calls outside `register()` are unaffected;
principle 4 lets implementation details read WordPress state.

**Configuration:** `hooks.contextFunctions` lists the request-context
functions: `is_admin`, `is_network_admin`, `is_user_admin`, `wp_doing_ajax`,
`wp_doing_cron`, `wp_is_json_request`, `is_customize_preview`, `is_singular`,
`is_front_page`, `is_home` and `is_archive`. `hooks.timingFunctions` lists the
hook-timing functions: `did_action`, `doing_action`, `did_filter`,
`doing_filter`, `current_action` and `current_filter`.

**Fix:** for request conditions, implement `Conditional` and return the answer
from `is_needed()`. The plugin then never builds the service in a request
that does not need it. For hook timing, implement `Delayed` and return the
action from `get_registration_action()`, so the plugin defers the service
until that action. The two checks have separate messages under the same
identifier.

**Patterns:** [I need a service only in some requests](patterns.md#i-need-a-service-only-in-some-requests), [I need to hook in later](patterns.md#i-need-to-hook-in-later).

### `architecture.queryContextNeedsDelay`

**Principle:** [7. Show the alternative to WordPress anti-patterns](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns),
and the capability interfaces of [the core idea](design-principles.md#how-services-map-to-it):
the plugin waits until a service's condition can answer before building it.

**Checks:** when `is_needed()` of a class implementing `Conditional` calls a
configured query-dependent function directly, the class also implements
`Delayed`.

**Allows:** a `Conditional` that only calls request checks such as
`is_admin()`, classes that do not implement `Conditional`, and calls in other
methods, helpers, closures or arrow functions. Only direct calls in
`is_needed()` count. This rule checks for `Delayed`; proving which action
`get_registration_action()` returns is outside its scope (#82).

**Configuration:** `queryContextNeedsDelay.queryFunctions` lists the
query-dependent functions: `is_singular`, `is_single`, `is_page`,
`is_front_page`, `is_home`, `is_archive`, `is_search`, `is_404`,
`is_attachment`, `is_feed` and `is_main_query`. The capability interfaces'
namespace comes from `dependencyDirection.infrastructure`.

**Fix:** implement `Delayed` and return `wp` or a later action from
`get_registration_action()`. The plugin waits for that action before asking
`is_needed()`, so the query has run when the condition reads it. See
[I need to hook in later](patterns.md#i-need-to-hook-in-later) and
[I need a service only in some requests](patterns.md#i-need-a-service-only-in-some-requests).

### `architecture.prefixedNames`

**Principle:** [13. One plugin, to arbitrary complexity](design-principles.md#13-one-plugin-to-arbitrary-complexity):
everything public carries the plugin's prefix, so several plugins built from
this scaffold run side by side.

**Checks:** the names the plugin creates in WordPress's shared spaces start
with the prefix for their kind:

| Kind | Functions | Name | Default prefix |
| --- | --- | --- | --- |
| `hooks` | `do_action`, `do_action_ref_array`, `apply_filters`, `apply_filters_ref_array` | 1st argument | `mwpd.basic_scaffold.` |
| `options` | `add_option`, `update_option`, `delete_option`, `get_option` and their `_site_option` variants | 1st argument | `mwpd_basic_scaffold_` |
| `transients` | `set_transient`, `get_transient`, `delete_transient` and their `_site_transient` variants | 1st argument | `mwpd_basic_scaffold_` |
| `meta` | `register_meta`, `register_post_meta`, `register_term_meta` | meta key, the 2nd argument | `mwpd_basic_scaffold_` |
| `assets` | `wp_register_script`, `wp_register_style`, `wp_enqueue_script`, `wp_enqueue_style` | handle, the 1st argument | `mwpd-basic-scaffold-` |
| `postTypes` | `register_post_type` | 1st argument; full name at most 20 characters | `mwpd_bs_` |
| `taxonomies` | `register_taxonomy` | 1st argument; full name at most 32 characters | `mwpd_bs_` |
| `shortcodes` | `add_shortcode` | 1st argument | `mwpd_basic_scaffold_` |
| `restRoutes` | `register_rest_route` | namespace, the 1st argument | `mwpd/basic-scaffold` |
| `cron` | `wp_schedule_event`; `wp_schedule_single_event`, `wp_unschedule_event`; `wp_next_scheduled`, `wp_clear_scheduled_hook` | hook: 3rd; 2nd; 1st argument, respectively | `mwpd_basic_scaffold_` |
| `adminPages` | `add_menu_page`, `add_options_page`, `add_management_page`; `add_submenu_page` | menu slug: 4th; 5th argument, respectively | `mwpd-basic-scaffold-` |
| `settings` | `register_setting` | option group and option name, the 1st and 2nd arguments | `mwpd_basic_scaffold_` |
| `ajax` | `add_action` with a known `wp_ajax_` or `wp_ajax_nopriv_` action | the remainder after WordPress's action prefix | `mwpd_basic_scaffold_` |

A name passes when PHPStan knows it starts with the prefix, or when it is built
from one of the kind's prefix constants of the class itself, as in
`static::HOOK_PREFIX . static::SERVICES_FILTER`. A name the rule cannot work
out fails, so build names from the prefix. Arguments unpacked from a constant
array are read from the array. The length limits come from the installed
WordPress stubs' `register_post_type()` and `register_taxonomy()` declarations.
They apply to the full constant string, including its prefix. A name built
from a configured prefix constant skips the length check because its final
length may be unknown.

**Allows:** for calls that only read a name, such as `get_option()`,
`wp_enqueue_script()` without a source, or firing a hook, the names WordPress
owns that the kind lists as allowed. `wp_next_scheduled()` is also a read;
scheduling and clearing cron events create or change the plugin's own names.
`add_filter()` and non-AJAX `add_action()` subscriptions are unchecked because
they subscribe to names other code owns. Only `add_action()` names known to
start with `wp_ajax_` or `wp_ajax_nopriv_` are checked, and their remainder
must carry the AJAX prefix. A known AJAX wrapper with an unresolved remainder
fails with the usual "cannot check" message.

**Configuration:** `prefixedNames.<kind>` sets the kind's `prefix`, its
prefix `constants` and the `allowed` names. A kind absent from configuration
is skipped. The defaults above follow WordPress conventions: underscores for
stored keys, shortcodes, cron hooks, settings and AJAX actions; hyphens for
asset handles and admin slugs; and the path-like `mwpd/basic-scaffold` for REST
namespaces (for example, `mwpd/basic-scaffold/v1`). Post types and taxonomies
use the shorter `mwpd_bs_` so the prefix leaves room for a name within their
limits; the option prefix alone is already 20 characters. Hooks retain
`mwpd.basic_scaffold.` and the `HOOK_PREFIX` constant. A plugin generated from
the scaffold renames these settings together with `HOOK_PREFIX` and
`SERVICE_PREFIX` in its composition root.

**Fix:** start the name with the prefix, or build it from the prefix
constant. Shorten overlong post type and taxonomy names, including the prefix,
to the WordPress limit.

**Pattern:** [I need a name in a space WordPress shares](patterns.md#i-need-a-name-in-a-space-wordpress-shares).

### `architecture.noSuppression`

**Principle:** the [hard rule](../AGENTS.md#hard-rules) not to weaken the
gates.

**Checks:** no inline `@phpstan-ignore`, `@phpstan-ignore-line` or
`@phpstan-ignore-next-line` in the main plugin file, `src/`, `views/` or
`tests/php/` names an architecture rule. `phpstan.neon.dist`, and any
baseline it includes, has no `ignoreErrors` entry for one, and keeps
`reportUnmatchedIgnoredErrors` on, so an ignore cannot outlive what it hid.

**Allows:** the fixtures of the architecture rules, which break them on
purpose. Ignores of other PHPStan, Psalm or PHPCS errors are not checked.

**Configuration:** none.

**Fix:** fix the code. If the rule is wrong for a real case, open an issue
with `needs: decision`; an exception the maintainer accepts becomes an
allowance in the rule's configuration.

An ignore counts as aimed at a rule when it names its identifier, or when its
message pattern matches, or spells out, one of the phrases the rule's manifest
entry lists as `signatures`. An ignore limited to other identifiers does not
count. The check reads the configuration the way PHPStan does: replacing keys
such as `ignoreErrors!`, and includes given relative, absolute or with
`%currentWorkingDirectory%`.

`RuleRegistrationTest` also keeps the rules, `RuleManifest` and this page in
step:

- every identifier constant has a manifest entry;
- every entry names what checks it, and that is a PHPStan rule registered in
  `tests/php/PHPStan/rules.neon` or a test method that exists;
- every PHPStan rule is registered, and named by an entry;
- every signature still appears in a rule's messages;
- no rule spells an identifier out instead of using its constant;
- every section on this page belongs to a rule in the manifest;
- every rule a pattern lists under **Rules** is in the manifest, and leads
  back to the pattern, as its `pattern` or through a link in its section on
  this page; and every rule's `pattern` lists the rule. `RuleManifestTest`
  checks that the `pattern` anchor exists.

### `architecture.compositionRootContracts`

**Principle:** [5. One composition root builds the object graph](design-principles.md#5-one-composition-root-builds-the-object-graph),
[11. Lifecycle steps are idempotent](design-principles.md#11-lifecycle-steps-are-idempotent),
and the capability interfaces of [the core idea](design-principles.md#how-services-map-to-it).

**Checks:** the real composition root, and every service it declares, built
through the plugin's own injector configuration:

- every declaration, a class name or a callable that returns one, is a class
  implementing `Service`;
- a `Delayed` service names an action to wait for, and a `HasDependencies`
  service only depends on services the root declares;
- the plugin accepts its own injector configuration, which it validates as
  it applies it, for example that each binding points to a subtype;
- the plugin can build every service from its own bindings, arguments and
  delegations, and what it builds is a `Service`;
- building a service adds, removes or fires no hook, and registers no
  activation or deactivation hook, not even through a collaborator its
  constructor creates;
- every declared service implementing `Registerable`, `Activateable` or
  `Deactivateable` can run `register()`, `activate()` or `deactivate()` twice
  without changing the recorded state on the second call. The check records
  hook additions and removals, option and transient writes, and post, user,
  term and comment meta writes. Failures name the service, method and effect;
  exceptions in either call are reported as problems.

**Allows:** the tests stub the WordPress functions the wiring calls, such as
`get_post()`, and use stand-ins for WordPress classes such as `WP_Post`. They
never add a binding the composition root does not have. WordPress writes are
modelled in memory: adding an existing option or unique meta row is a no-op,
setting a value replaces it, and non-unique meta additions append. Hooks are
keyed by hook name, priority and callback identity, as in WordPress. Re-adding
the same callback at the same priority replaces the entry; a fresh closure,
a different object or method, or a different priority adds an entry. Removing
an existing entry changes state; removing a missing entry leaves it unchanged.
Actions and filters share hook entries, and fired hooks retain their call
history. This does not check the plugin's own
`register_services()` orchestration (#26), which services activation and
deactivation reach (`tests/php/Unit/ServiceBasedPluginTest.php` covers that),
or database and filesystem effects Brain Monkey cannot see.

**Configuration:** `RepositoryLayout::COMPOSITION_ROOT` names the composition
root. A service added to it is checked without changing the test.

**Fix:** bind what a service needs in the composition root, declare the
services it depends on, and keep constructors to storing dependencies. Guard
each lifecycle step so a second run does nothing, or make it set rather than
add.

**Patterns:** [I need to add a feature](patterns.md#i-need-to-add-a-feature), [I need to set something up when the plugin is activated](patterns.md#i-need-to-set-something-up-when-the-plugin-is-activated).

### `architecture.capabilitiesOnServices`

**Principle:** [5. One composition root builds the object graph](design-principles.md#5-one-composition-root-builds-the-object-graph),
and the capability interfaces of [the core idea](design-principles.md#how-services-map-to-it).

**Checks:** a class implementing `Registerable`, `Conditional`, `Delayed`,
`HasDependencies`, `Activateable` or `Deactivateable` also implements `Service`.
A `Delayed` class also implements `Registerable`: waiting for an action only
makes sense when the service has hooks to register. Inherited interfaces count,
including on abstract classes.

**Allows:** interfaces and traits, ordinary classes with no lifecycle
capabilities, and services implementing the capabilities they need. The
infrastructure and the configured composition-root classes orchestrate services
and are outside this check.

**Configuration:** `capabilitiesOnServices.service` names the service marker,
`capabilities` lists the lifecycle interfaces, and `delayed` and `registerable`
name the two interfaces whose combination is checked. All are fully qualified
names, renamed together with the plugin's namespace. The orchestration
exclusions come from `dependencyDirection.infrastructure` and
`dependencyDirection.compositionRoot`.

**Fix:** implement `Service` so the plugin can orchestrate the class; for a
`Delayed` class, implement `Registerable` and add its hooks in `register()`.
Declare the service in the composition root as shown in
[I need to add a feature](patterns.md#i-need-to-add-a-feature).

### `architecture.servicesDeclared`

**Principle:** [5. One composition root builds the object graph](design-principles.md#5-one-composition-root-builds-the-object-graph).

**Checks:** every concrete class in a feature subsystem that implements
`Service`, directly or through inheritance, is declared in the composition
root's `get_service_classes()`. Callable declarations are resolved through the
root's configured injector, just as in `compositionRootContracts`.

**Allows:** abstract classes, interfaces, traits, non-service classes, classes
directly in the plugin's root namespace, the infrastructure, exceptions and
excluded namespaces. Services built by a factory or another route are allowed
when explicitly listed in `RepositoryLayout::SERVICES_BUILT_ELSEWHERE`, with a
description of that route containing more than whitespace. Services supplied
by other plugins through the `services` filter are outside this check.

**Configuration:** production paths and `SERVICES_BUILT_ELSEWHERE` come from
`RepositoryLayout`; `COMPOSITION_ROOT` selects the root. The plugin namespace,
`excludedNamespaces`, `dependencyDirection.infrastructure` and
`exceptions.namespace` come from `parameters.architecture` in
`phpstan.neon.dist`, so generated plugins keep the check after renaming.

**Fix:** declare the named class in `get_service_classes()`, or list it in
`RepositoryLayout::SERVICES_BUILT_ELSEWHERE` with the factory or other route
that builds it. See [I need to add a feature](patterns.md#i-need-to-add-a-feature).
