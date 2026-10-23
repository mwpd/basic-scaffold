# Patterns

The [design principles](design-principles.md) say why the scaffold is built
the way it is, and the [architecture rules](architecture-rules.md) say exactly
what the checks reject. This page answers the question in between, the one you
arrive with: **"I need X. How do I do that here?"**

Each section is named after a need, in the words you would search for. Many
of those needs have a well-known answer elsewhere in WordPress or PHP, often
one this scaffold rejects, so every section has the same parts:

- **What people reach for**: the common answer.
- **Why not here**: the principles and the rules that reject it.
- **Instead**: the scaffold's pattern. Where one need hides several, the
  section tells them apart.
- **Proven by**: code in the scaffold that follows the pattern and passes the
  checks.
- **Rules**: the checks that enforce it. A rule still being built names its
  issue.

If a check stops you, its message names the principle and the rule. Look up
what you were trying to do here before working around it. If no section fits,
the need may be new: ask on an issue rather than inventing a workaround.

## Contents

- [I need to add a feature](#i-need-to-add-a-feature)
- [I need to add hooks](#i-need-to-add-hooks)
- [I need a service only in some requests](#i-need-a-service-only-in-some-requests)
- [I need to hook in later](#i-need-to-hook-in-later)
- [I need to set something up when the plugin is activated](#i-need-to-set-something-up-when-the-plugin-is-activated)
- [I need to create objects on demand](#i-need-to-create-objects-on-demand)
- [I need to choose an implementation at runtime](#i-need-to-choose-an-implementation-at-runtime)
- [I need a service locator](#i-need-a-service-locator)
- [I need a singleton](#i-need-a-singleton)
- [I need to reach the plugin from a static WordPress callback](#i-need-to-reach-the-plugin-from-a-static-wordpress-callback)
- [I need WordPress state, such as the current post](#i-need-wordpress-state-such-as-the-current-post)
- [I need a default collaborator that can be replaced](#i-need-a-default-collaborator-that-can-be-replaced)
- [I need to start simple and add implementations later](#i-need-to-start-simple-and-add-implementations-later)
- [I need to print output](#i-need-to-print-output)
- [I need to report a failure](#i-need-to-report-a-failure)
- [I need to run code when the plugin file loads](#i-need-to-run-code-when-the-plugin-file-loads)
- [I need a name in a space WordPress shares](#i-need-a-name-in-a-space-wordpress-shares)

## I need to add a feature

**What people reach for:** one more method on the main plugin class, or one
more `if` in an existing service, so that the class grows with every feature.

**Why not here:** [the core idea](design-principles.md#the-core-idea-complexity-that-grows-linearly)
and [principle 4](design-principles.md#4-solid-at-the-top-wordpress-at-the-edges):
each new condition threaded through existing code multiplies the cases that
can go wrong. A god class is in the
[principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
table of anti-patterns.

**Instead:** write one new service for the feature, in its own subsystem
namespace, and declare it in the composition root. The service decides for
itself when it is needed and when it hooks in, through the capability
interfaces in the sections below. No other code changes.

```php
// src/BasicScaffoldPlugin.php
protected function get_service_classes(): array {
	return [
		self::SAMPLE_BACKEND_SERVICE_ID => SampleBackendService::class,
		self::SAMPLE_LOOP_SERVICE_ID    => SampleLoopService::class,
		self::REPORTS_SERVICE_ID        => ReportsService::class, // The new feature (illustrative).
	];
}
```

**Proven by:** `src/BasicScaffoldPlugin.php:59-66` declares the two sample
services; neither knows about the other.

**Rules:** `architecture.compositionRootContracts` builds every declared
service; `architecture.dependencyDirection` keeps a subsystem on the
infrastructure's interfaces. `architecture.capabilitiesOnServices` keeps lifecycle
capabilities on services, and `architecture.servicesDeclared` catches an
undeclared subsystem service.

## I need to add hooks

**What people reach for:** `add_action()` in the constructor, or at the top of
the file.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
creating an object must not change global state, and including a file must not
do anything.

**Instead:** implement `Registerable` and add the hooks in `register()`. The
plugin calls it once, at the right point in the lifecycle. Activation and
deactivation work the same way, through `Activateable` and `Deactivateable`.

```php
final class SampleBackendService implements Service, Registerable, Conditional {

	public function __construct( ViewFactory $view_factory ) {
		$this->view_factory = $view_factory;
	}

	public function register(): void {
		\add_action( 'admin_notices', [ $this, 'render_notice' ], 10, 0 );
	}
}
```

**Proven by:** `src/SampleSubsystem/SampleBackendService.php:60-77`.

**Rules:** `architecture.noHooksInConstructor`,
`architecture.hooksInLifecycleMethods`, `architecture.constructorsOnlyAssign`.
Service constructors only assign parameters to properties, with optional
poka-yoke defaults. Move other work into `register()` or a callback, or into a
factory if it is construction logic.

## I need a service only in some requests

**What people reach for:** `if ( is_admin() ) { add_action( … ); }`, wrapped
around hook calls throughout the code.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns),
and [the core idea](design-principles.md#how-services-map-to-it): the
condition belongs to the service, stated once.

**Instead:** implement `Conditional` and answer in the static `is_needed()`.
The plugin asks before it builds the service, so in a request that does not
need it, the service never exists.

```php
public static function is_needed(): bool {
	return \is_admin() && ! \wp_doing_ajax();
}
```

**Proven by:** `src/SampleSubsystem/SampleBackendService.php:45-52`.

When the answer depends on the query, such as `is_singular()`, also implement
`Delayed` and wait for `wp` or a later action as shown in
[I need to hook in later](#i-need-to-hook-in-later).

**Rules:** `architecture.noContextChecksInRegister`,
`architecture.queryContextNeedsDelay`.

## I need to hook in later

**What people reach for:** hooking into `plugins_loaded` or `init` just to add
another callback for `wp`, or checking `did_action()` before hooking.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns),
and the lifecycle facts in [the core idea](design-principles.md#what-the-wordpress-lifecycle-means-here):
many conditions only answer once a certain action has run. `is_singular()`, for
example, means nothing before the query.

**Instead:** implement `Delayed` and name the action in
`get_registration_action()`. The plugin waits for it, or registers at once if
it has already fired, and only then asks `is_needed()` and builds the service.

```php
final class SampleLoopService implements Service, Registerable, Conditional, Delayed {

	private const REGISTRATION_HOOK = 'wp';

	public static function is_needed(): bool {
		return \is_singular(); // Answers correctly, because the query has run.
	}

	public static function get_registration_action(): string {
		return self::REGISTRATION_HOOK;
	}
}
```

**Proven by:** `src/SampleSubsystem/SampleLoopService.php:28-72`.

**Rules:** `architecture.noContextChecksInRegister`,
`architecture.queryContextNeedsDelay`, `architecture.registrationActionKnown`.
Hook-timing checks such as `did_action()` in `register()` are reported, as are
unknown registration actions. Whether a known action fires in the service's
request type or runs late enough for its condition remains a review concern.

## I need to set something up when the plugin is activated

**What people reach for:** `register_activation_hook()` next to the code that
needs it, calling the `init` callback from the activation hook so a post type
exists before the flush, or a hard `flush_rewrite_rules()` on every
activation.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
and [the core idea](design-principles.md#how-services-map-to-it): the main
plugin file registers the activation and deactivation hooks once, and each
service says for itself what it does there. Calling `register()` early would
also break the promise of `Delayed`, that a service registers at its own
action.

**Instead:** implement `Activateable` and `Deactivateable`. The plugin calls
every service implementing them, in the order they are configured, including
services it stores lazily and `Delayed` services whose action has not fired
yet. A service that is not registered yet is built for the call alone, with
its usual constructor collaborators; its `register()` still runs at its own
action. Then the plugin asks WordPress for one soft flush of the rewrite
rules.

So a service with rewrite rules sets them up in `activate()` and removes them
in `deactivate()`, rather than relying on its normal registration having run
in that request. Whether it has depends on how the plugin was activated.

```php
final class BookPostType implements Service, Registerable, Delayed, Activateable, Deactivateable {

	public static function get_registration_action(): string {
		return 'init';
	}

	public function register(): void {
		$this->register_post_type();
	}

	public function activate(): void {
		$this->register_post_type(); // Present for the flush that follows.
	}

	public function deactivate(): void {
		\unregister_post_type( self::POST_TYPE ); // Gone for the flush that follows.
	}
}
```

Three things to know:

- **The flush is soft.** It rebuilds the rules WordPress stores, but does not
  write server configuration such as `.htaccess`. A service that needs that
  calls `\flush_rewrite_rules( true )` at the end of its own `activate()` or
  `deactivate()`. WordPress postpones any flush requested before `wp_loaded`
  until then, and does a hard one if any of the requests asked for it.
  Plugins generated before #25 got a hard flush by default; one that relies
  on it to regenerate server configuration adds that call when it updates.
- **`Conditional` still applies.** A service whose `is_needed()` is false in
  the activation request is neither built nor called. Setup or cleanup that
  must run whatever the condition belongs in a separate service without it.
- **`HasDependencies` does not order lifecycle calls.** It only governs
  registration, and no dependency is registered early for a lifecycle call.
  Steps that must run in sequence belong in one service that runs them in
  that order.

**Proven by:** `tests/php/Fixture/Lifecycle/RewriteLifecycleService.php`,
exercised by `tests/php/Unit/ServiceBasedPluginTest.php`.

**Rules:** `architecture.compositionRootContracts` runs `activate()` and
`deactivate()` twice and reports a second run that changes anything, so guard
each step or make it set rather than add.

## I need to create objects on demand

**What people reach for:** `new Thing()` inside the class that needs it.

**Why not here:** [principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph):
the composition root builds the object graph, and the class that uses an
object never creates it.

**Instead:** when an object can only be made later, or is needed once per use,
receive a factory and ask it each time. The factory may use `new` internally:
creating things is its whole job.

```php
// The consumer receives the factory, typed to its interface …
public function __construct( ViewFactory $view_factory ) {
	$this->view_factory = $view_factory;
}

// … and asks it for a fresh view each time it renders.
echo $this->view_factory->create( 'views/test-backend-service' )->render( [ … ] );
```

```php
// The factory creates its product.
final class TemplatedViewFactory implements Service, ViewFactory {

	public function create( string $relative_path ): View {
		if ( $this->locations === [] ) {
			$this->locations = $this->get_default_locations();
		}

		return new TemplatedView( $relative_path, $this, $this->locations );
	}
}
```

`TemplatedViewFactory` only stores the optional locations in its constructor.
When none are passed, it reads the theme directories on the first `create()`
call and keeps those locations for later calls. Explicit locations keep their
order and bypass theme lookups.

When writing a dedicated factory is overkill, receive the `Injector` itself
and call `make()` on it. A constructor parameter typed `Injector` gets the
plugin's own injector, whichever implementation the plugin was given; the
infrastructure sets that up, so the composition root needs no configuration
for it. A service only ever calls `make()` on the injector: bindings, sharing
and delegations stay in the composition root.

```php
public function __construct( Injector $injector ) {
	$this->injector = $injector;
}

// … later, once per use:
$report = $this->injector->make( Report::class );
```

**Proven by:** `src/SampleSubsystem/SampleBackendService.php:60-66` and
`:82-89`, `src/Infrastructure/View/TemplatedViewFactory.php:29` and `:57-63`,
bound at `src/BasicScaffoldPlugin.php:95`. The injector is made injectable at
`src/Infrastructure/ServiceBasedPlugin.php:346-349`, proven by
`tests/php/Unit/ServiceBasedPluginTest.php:685-737`.

**Rules:** `architecture.newOnlyWhereAllowed`, `architecture.noServiceLocator`;
`architecture.dependencyDirection` keeps the consumer on the interface.

## I need to choose an implementation at runtime

**What people reach for:** a switch over a type name that calls `new`, or a
container lookup by a computed identifier, such as
`$container->get( 'gateway.' . $type )`.

**Why not here:** [principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)
rules out `new` of collaborators, and a lookup by computed identifier is a
service locator. [Principle 8](design-principles.md#8-types-name-concepts-not-just-data)
wants the choice typed.

**Instead:** a typed factory or registry for that one concept. It takes the
key as a value, returns the interface, and is injected like any other
collaborator. The example below is illustrative; the scaffold does not ship a
registry yet.

```php
interface PaymentGateways {
	public function get( GatewayKey $key ): PaymentGateway;
}

final class CheckoutService implements Service, Registerable {

	public function __construct( PaymentGateways $gateways ) {
		$this->gateways = $gateways;
	}
}
```

The composition root binds `PaymentGateways` to an implementation, which
builds or receives the gateways it knows. Nothing outside it knows the
concrete classes.

**Proven by:** no registry in the scaffold yet. `ViewFactory` has the same
outer shape (`src/Infrastructure/ViewFactory.php:33`): it is injected, takes a
value (a view path) and returns an interface (`View`). It always builds the
same implementation, though; the path only picks the template file.

**Rules:** `architecture.newOnlyWhereAllowed`,
`architecture.dependencyDirection`, `architecture.noServiceLocator`. Receive
the typed registry through the constructor instead of the service container.

## I need a service locator

**What people reach for:** a static `Services::get( 'id' )` that any code can
call. All three plugins known to be built on this scaffold added one (#22).

**Why not here:** [principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)
and the singleton row of [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
a locator hides dependencies and lets any code reach any object. A locator
that keeps its own registry needs static state, which
`architecture.noStaticState` already rejects. One that only forwards to the
plugin factory, `BasicScaffoldPluginFactory::create()->get_container()->get( $id )`,
holds no state of its own. `architecture.pluginFactoryOnlyAtEdges` rejects
that factory call inside the object graph, and `architecture.noServiceLocator`
keeps the container out of feature subsystems.

**Instead:** find the need behind it. Each one has its own answer:

| You need | Use |
| --- | --- |
| A collaborator, known in advance | Constructor injection: [I need to add a feature](#i-need-to-add-a-feature) |
| An object created later or per use | [A factory, or `Injector::make()`](#i-need-to-create-objects-on-demand) |
| One of several implementations, chosen by a value | [A typed registry](#i-need-to-choose-an-implementation-at-runtime) |
| The same instance in several places | [`share()`](#i-need-a-singleton) |
| Access from code WordPress calls statically, outside the object graph | [An edge bridge](#i-need-to-reach-the-plugin-from-a-static-wordpress-callback) |
| A public PHP API for themes or other plugins | Not settled yet: #93 |

**Proven by:** the sections linked above.

**Rules:** `architecture.noStaticState`, for locators with a registry of
their own; `architecture.noServiceLocator`, for container dependencies and
reconfiguring the injector from a subsystem; `architecture.pluginFactoryOnlyAtEdges`,
for plugin factory calls outside the main file and listed bridges. Receiving
`Injector` and calling `make()` remains supported for on-demand creation.

## I need a singleton

**What people reach for:** a class with a private constructor, a static
`$instance` and `::instance()`.

**Why not here:** [principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)
and [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
static state is global state, hidden behind a class name, and it cannot be
replaced in a test.

**Instead:** let the injector share the instance. List the class in the
composition root's shared instances, and every object that asks for it
receives the same one. The class itself stays an ordinary class.

```php
// src/BasicScaffoldPlugin.php
protected function get_shared_instances(): array {
	return [
		ReportCache::class, // Illustrative: built once, injected wherever it is asked for.
	];
}
```

The one exception is the plugin itself, which WordPress needs to reach from
outside the object graph: see the next section.

**Proven by:** `src/BasicScaffoldPlugin.php:136-146` is where shared instances
are declared; `Injector::share()` (`src/Infrastructure/Injector.php:78`) is
what the plugin calls for each one.

**Rules:** `architecture.noStaticState`.

## I need to reach the plugin from a static WordPress callback

**What people reach for:** a global `$my_plugin` variable, or a singleton.

**Why not here:** [principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)
and [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns).
Some WordPress entry points still need static access: `register_uninstall_hook()`
only accepts a static method or a function, and the activation hooks run
outside the normal request flow.

**Instead:** an *edge bridge*. The plugin factory is the one place allowed to
keep the plugin instance; a bridge is a static callable that gets the plugin
through it and delegates at once to instance methods:

```php
\register_activation_hook( __FILE__, static function () {
	BasicScaffoldPluginFactory::create()->activate();
} );
```

A bridge outside the main plugin file follows the same contract:

- a `final` class with static methods and no state of its own;
- each method gets the plugin through the factory and delegates immediately,
  with no logic of its own;
- it is listed in the configuration, so every way into the object graph from
  outside is visible and reviewed;
- it is the point where WordPress hands over control, so it has to handle
  [principle 10](design-principles.md#10-errors-are-exceptions) there: a call
  before `plugins_loaded`, a `Conditional` service that is not registered in
  this request, or any other failure becomes a dedicated exception, caught
  before control goes back to WordPress. The activation hooks do not catch
  yet; that is #27.

The uninstall bridge (#34) is the first bridge the scaffold itself adds.

**Proven by:** `basic-scaffold.php:119-131`, and the factory at
`src/BasicScaffoldPluginFactory.php:38-56`.

**Rules:** `architecture.noStaticState` allows static state only in the
factory. [`architecture.pluginFactoryOnlyAtEdges`](architecture-rules.md#architecturepluginfactoryonlyatedges)
reports calls to the factory outside the main file and methods listed in
`parameters.architecture.pluginFactoryOnlyAtEdges.bridges`.

## I need WordPress state, such as the current post

**What people reach for:** `global $post;`, `global $wpdb;`, or `get_post()`
deep inside a service.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
(collaborators pulled from globals) and
[principle 9](design-principles.md#9-never-trust-what-wordpress-hands-over):
what WordPress hands over is checked once, at the boundary.

**Instead:** declare what you need in the constructor, and let a delegation in
the composition root produce it. The delegation is the boundary: it fetches
the value, checks it, and fails with a dedicated exception when it is not
what the service needs.

```php
// src/BasicScaffoldPlugin.php
WP_Post::class => static function (): WP_Post {
	$post = \get_post();

	if ( ! $post instanceof WP_Post ) {
		throw FailedToFindPost::for_current_request();
	}

	return $post;
},
```

```php
// The service only says what it needs.
public function __construct( ViewFactory $view_factory, WP_Post $post ) { … }
```

Calling a WordPress function inside an implementation detail is still fine
([principle 4](design-principles.md#4-solid-at-the-top-wordpress-at-the-edges)).
The delegation is for values a service depends on.

**Proven by:** `src/BasicScaffoldPlugin.php:159-192` and
`src/SampleSubsystem/SampleLoopService.php:81`.

For request input, a boundary adapter reads the superglobals, sanitizes and
normalizes them into an internal type, and supplies that value to the service.
List the adapter class in `parameters.architecture.noGlobalState.inputAdapters`;
this permits request superglobals, never `global` or `$GLOBALS`.

**Rules:** `architecture.newOnlyWhereAllowed` (WordPress classes such as
`WP_Query` come from the injector too), and `architecture.noGlobalState` for
`global`, `$GLOBALS` and request input outside listed adapters.

## I need a default collaborator that can be replaced

**What people reach for:** `new DefaultThing()` in the constructor, which can
never be swapped, or a required parameter that every caller has to wire up.

**Why not here:** [principle 3](design-principles.md#3-defaults-out-of-the-box-poka-yoke):
the default ships built in, and replacing it is a matter of passing your own.

**Instead:** an optional constructor parameter typed to the interface, with the
built-in implementation as the fallback. The fallback is the only place the
default is named.

```php
public function __construct( string $path, ViewFactory $view_factory, ?DebugMode $debug_mode = null ) {
	$this->debug_mode = $debug_mode ?? new WordPressDebugMode();
}
```

**Proven by:** `src/Infrastructure/View/SimpleView.php:73-76`, and the
injector and container defaults of `ServiceBasedPlugin`
(`src/Infrastructure/ServiceBasedPlugin.php:152-171`).

**Rules:** `architecture.newOnlyWhereAllowed` allows `new` in exactly this
shape: a nullable parameter typed to an interface the default implements.

## I need to start simple and add implementations later

**What people reach for:** an interface for everything up front, or names that
say what kind of type something is (`LoggerInterface`, `AbstractLogger`,
`LoggerImpl`).

**Why not here:** [principle 2](design-principles.md#2-self-contained-infrastructure-replaceable-by-design)
keeps implementations simple until a real need appears, and
[principle 8](design-principles.md#8-types-name-concepts-not-just-data) names
types after the concept.

**Instead:** start with one concrete class, named after the concept. When a
second implementation is needed, turn that name into the interface and give
each implementation a name that says how it differs (`Simple…`,
`WordPress…`, `Cached…`). Every type hint, `instanceof` and `catch` keeps
working. Only the composition root changes, because it is the only place that
creates collaborators (principle 5), and nothing extends a `final` class
(principle 6).

**Proven by:** the infrastructure's names: `DebugMode` and
`WordPressDebugMode` (`src/Infrastructure/Service/WordPressDebugMode.php`),
`Injector` and `SimpleInjector`, `ViewFactory` and `TemplatedViewFactory`.

**Rules:** `architecture.finalByDefault`, `architecture.namesWithoutTypeKind`.

## I need to print output

**What people reach for:** `echo '<div>' . $value . '</div>';` inside a
service.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
(HTML echoed from business logic) and
[principle 9](design-principles.md#9-never-trust-what-wordpress-hands-over):
output is escaped for the context it lands in.

**Instead:** render a view from the injected `ViewFactory` and echo the result.
In the template, values read as properties are escaped already. A value read
with `raw()` is the explicit exception: escape it yourself, with the `esc_*`
function for where it is printed.

```php
// views/test-loop-service.php
$current_post = $this->raw( 'post' );
?>
Post title: <?= esc_html( $current_post->post_title ) ?>
```

**Proven by:** `src/SampleSubsystem/SampleBackendService.php:82-89` and
`views/test-loop-service.php:7-17`.

**Rules:** `architecture.outputOnlyThroughViews` requires direct method
calls on typed `Renderable` or `View` objects when printing from services.
`architecture.rawIsEscaped` checks raw values in templates, and PHPCS
(WordPress Coding Standards) checks escaping.

## I need to report a failure

**What people reach for:** returning `false`, `null` or a `WP_Error`, calling
`error_log()` or `trigger_error()`, or throwing a bare `RuntimeException`.

**Why not here:** [principle 10](design-principles.md#10-errors-are-exceptions):
inside the plugin, every error is an exception, and exceptions never reach the
end user.

**Instead:** throw a dedicated exception from `src/Exception/`, created through
a named constructor that states the cause, and pass a caught exception on as
the previous one. Where control goes back to WordPress, catch it: loud with
debug mode on, degrade gracefully with it off.

```php
final class FailedToFindPost extends RuntimeException implements BasicScaffoldException {

	public static function for_current_request(): self {
		return new self(
			'There is no current post. A service that needs the current post has to wait for the main query, for'
			. ' example by implementing Delayed with the "wp" action, and only be needed on singular views.'
		);
	}
}
```

```php
try {
	include $this->path;
} catch ( Throwable $throwable ) {
	// … discard the partial output, then pass the cause on.
	throw FailedToLoadView::from_view_exception( $this->path, $throwable );
}
```

**Proven by:** `src/Exception/FailedToFindPost.php:27`,
`src/Infrastructure/View/SimpleView.php:98-124` and
`src/Exception/FailedToLoadView.php:39`.

**Rules:** `architecture.noWordPressErrorMechanisms`, `architecture.noFalseOnFailure`,
`architecture.throwOnlyPluginExceptions`, `architecture.exceptionShape`,
`architecture.exceptionsThroughNamedConstructors`,
`architecture.exceptionsKeepCause`.

## I need to run code when the plugin file loads

**What people reach for:** calls, `define()` or `add_action()` at the top of a
file in `src/`.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
and [principle 12](design-principles.md#12-extension-points-run-at-a-hook-never-at-include-time):
at include time, plugins loaded later have had no chance to hook in, and an
autoloaded file that does something on include does it whenever any class
loads it.

**Instead:** put the work in a service and let `register()` hook it in at the
right action. The main plugin file is the only file with side effects, and it
only loads the autoloader, hooks activation and deactivation, and registers
the plugin.

**Proven by:** `basic-scaffold.php:74-137`.

**Rules:** `architecture.noSideEffectsOnInclude`;
`architecture.bootstrapContract` limits the main file's hook API calls to
registering one `plugins_loaded` action and the activation and deactivation
hooks, leaving extension points for registration.

## I need a name in a space WordPress shares

**What people reach for:** a short, readable name such as `settings`,
`my_option` or `admin-script`.

**Why not here:** [principle 13](design-principles.md#13-one-plugin-to-arbitrary-complexity):
several plugins built from this scaffold run side by side, and a name without
the plugin's prefix can clash with any of them.

**Instead:** start every hook, option, transient, meta key, asset handle,
post type, taxonomy, shortcode, REST namespace, cron hook, admin page slug,
setting group/name, AJAX action and service identifier with the plugin's
prefix. Build hook names and service identifiers from the prefix constants.

```php
protected const HOOK_PREFIX = 'mwpd.basic_scaffold.';

public const SAMPLE_LOOP_SERVICE_ID = self::SERVICE_PREFIX . 'sample-subsystem.sample-loop-service';
```

**Proven by:** `src/BasicScaffoldPlugin.php:206-218`.

**Rules:** `architecture.prefixedNames` covers hooks, stored keys, asset
handles, post types, taxonomies, shortcodes, REST namespaces, cron hooks,
admin pages, settings and AJAX actions. Service identifiers and theme
override paths remain tracked in #29.
