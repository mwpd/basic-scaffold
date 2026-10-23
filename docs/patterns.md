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
- [I need to clean up when the plugin is uninstalled](#i-need-to-clean-up-when-the-plugin-is-uninstalled)
- [I need to create objects on demand](#i-need-to-create-objects-on-demand)
- [I need to choose an implementation at runtime](#i-need-to-choose-an-implementation-at-runtime)
- [I need a service locator](#i-need-a-service-locator)
- [I need a singleton](#i-need-a-singleton)
- [I need to reach the plugin from a static WordPress callback](#i-need-to-reach-the-plugin-from-a-static-wordpress-callback)
- [I need a public API for themes or other plugins](#i-need-a-public-api-for-themes-or-other-plugins)
- [I need WordPress state, such as the current post](#i-need-wordpress-state-such-as-the-current-post)
- [I need a default collaborator that can be replaced](#i-need-a-default-collaborator-that-can-be-replaced)
- [I need to start simple and add implementations later](#i-need-to-start-simple-and-add-implementations-later)
- [I need to print output](#i-need-to-print-output)
- [I need a template to ask for more than its context](#i-need-a-template-to-ask-for-more-than-its-context)
- [I need to report a failure](#i-need-to-report-a-failure)
- [I need to run code when the plugin file loads](#i-need-to-run-code-when-the-plugin-file-loads)
- [I need a name in a space WordPress shares](#i-need-a-name-in-a-space-wordpress-shares)
- [I need to see why a service did not run](#i-need-to-see-why-a-service-did-not-run)

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

**Proven by:** `src/BasicScaffoldPlugin.php:82-100` declares the two sample
services; neither knows about the other.

**Rules:** `architecture.compositionRootContracts` builds every declared
service; `architecture.dependencyDirection` keeps a subsystem on the
infrastructure's interfaces. `architecture.capabilitiesOnServices` keeps lifecycle
capabilities on services, and `architecture.servicesDeclared` catches an
undeclared subsystem service.

**Migration:** When updating a generated plugin, service declaration resolvers now all run
once, in declaration order, before any configured service is scheduled or
checked for dependencies. Prefer resolvers that compute class names without
depending on earlier services being registered. The whole filtered map is
validated first so dependency overrides can trust every entry, including later
ones. A later invalid declaration can now fail before an earlier unknown
dependency, and prevents earlier services from being scheduled. This does not
roll back resolver side effects or the injector setup that already ran.

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

When the answer depends on the admin screen, such as `get_current_screen()`,
also implement `Delayed` and wait for `current_screen` or a later admin
action. WordPress has not set the screen at `plugins_loaded`, when the
plugin normally asks `is_needed()`.

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
`get_registration_priority()` places the service among the action's other
callbacks; the `DefaultRegistrationPriority` trait supplies WordPress's default
of 10 for a service that has no reason to choose.

```php
final class SampleLoopService implements Service, Registerable, Conditional, Delayed {

	use DefaultRegistrationPriority; // Registers at priority 10 on the action.

	private const REGISTRATION_HOOK = 'wp';

	public static function is_needed(): bool {
		return \is_singular(); // Answers correctly, because the query has run.
	}

	public static function get_registration_action(): string {
		return self::REGISTRATION_HOOK;
	}
}
```

A service that has to run before or after other callbacks on its action
implements the method itself instead of using the trait:

```php
public static function get_registration_priority(): int {
	return 20; // After the callbacks at the default priority of 10.
}
```

**Dependencies take precedence over the priority.** A `Delayed` service that
also implements `HasDependencies` never registers before its dependencies.
When they are ready before its action, it still waits for the action and
registers at its declared priority. When they are only ready after its
priority on the action has passed, or after the action itself, it registers
as soon as they are, at the end of the action they arrive on. The plugin does
not reject that combination, and does not run an action again to make up for a
priority that has passed.

**Proven by:** `src/SampleSubsystem/SampleLoopService.php:28-80`, and the
priority and dependency cases in
`tests/php/Unit/ServiceBasedPluginTest.php:533-660`.

**Rules:** `architecture.noContextChecksInRegister`,
`architecture.queryContextNeedsDelay`, `architecture.registrationActionKnown`.
Hook-timing checks such as `did_action()` in `register()` are reported, as are
unknown registration actions. Whether a known action fires in the service's
request type or runs late enough for its condition remains a review concern.

**Migration:** `get_registration_priority()` is a new method on `Delayed`, so a
generated plugin's existing `Delayed` services stop loading until they declare
it: PHP reports a fatal error for a class that does not implement every
interface method. Add `use DefaultRegistrationPriority;` (from the
`Infrastructure` namespace) to each of them to keep the priority of 10 they
were registered at before, or implement the method to choose another one.

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

### On multisite: in every site, or once for the network

`activate()` and `deactivate()` run once, in the site the plugin is
activated in. When the work belongs in each site, or once for a whole
network, implement the scoped interfaces instead. Each one receives the
`SiteContext` or `NetworkContext` it runs in:

| Interface | Called |
| --- | --- |
| `SiteActivateable`, `SiteDeactivateable` | In the current site, or in every site of the network for a network activation or deactivation, with that site switched to. A network deactivation skips a site that keeps the plugin activated on its own, and deactivating one site tears nothing down while the plugin stays network-active. |
| `NetworkActivateable`, `NetworkDeactivateable` | Once for a network activation or deactivation, in the network's main site. Setup runs for the network first, then the sites; teardown runs the sites first. |
| `SiteInitializable` | In a new site, once core has set it up, when the plugin is network-active on the new site's network. |
| `SiteUninitializable` | In a site about to be deleted, before core drops its tables, whether or not the plugin is still active there. |

```php
final class ReportsTable implements Service, SiteActivateable, SiteInitializable {

	public function activate_site( SiteContext $context ): void {
		$this->create_table(); // In the site that is switched to.
	}

	public function initialize_site( SiteContext $context ): void {
		$this->create_table(); // A site added after the network activation.
	}

	private function create_table(): void { /* … */ }
}
```

Things to know:

- **A scoped interface replaces the parameterless one.** A service that
  implements any scoped interface of an event no longer receives `activate()`
  or `deactivate()` for that event, in any scope, even where its scoped
  interface does not apply: one with `Activateable` and `NetworkActivateable`
  gets nothing on an activation of a single site. Setup that several methods
  share goes into a private method each of them calls, as above, so it never
  runs twice.
- **Each site gets an object graph of its own.** The plugin builds it after
  switching to the site: a fresh injector, the composition root's
  configuration and services applied there with their filters, and
  `is_needed()` asked there. Shared instances are shared within that site
  only, nothing goes into the plugin's container and nothing is registered.
  Callable declarations in the configuration run for each site too, so they
  should only work out class names. Whether the sites are visited at all is
  decided by class, from the services configured in the site the event starts
  in, so a scoped service that only another site's filters add is not
  reached.
- **Address network options by ID.** Switching sites does not switch networks,
  so use `$context->network_id()` with `get_network_option()` and its
  siblings.
- **The work is done when the call returns.** Sites are visited one after the
  other in the activation request, looked up in pages: that bounds memory, not
  time, so a large network can run into the time limit. A custom `SiteRunner`
  passed to the plugin constructor replaces the default, for example one that
  is driven from WP-CLI. A plugin given a custom injector also passes an
  `InjectorFactory` that creates it; otherwise a scoped service fails with
  `InvalidConfiguration`. Both are optional trailing constructor parameters,
  as in `new BasicScaffoldPlugin( true, null, null, $runner )`.
- **A failure stops the event, visibly.** The first exception propagates after
  the site context is restored, in debug mode and in production alike
  ([principle 10](design-principles.md#10-errors-are-exceptions)). Sites
  already done stay done, so run the activation again once the cause is fixed.
  A failure in a new or deleted site can interrupt WordPress's own creation or
  deletion of that site.
- **New and deleted sites are only reached where the plugin is loaded.** A
  site created from a network where the plugin is not active, or a site
  deleted from the network admin while the plugin is only active on that one
  site, is not reached, because WordPress does not load the plugin in that
  request. Nothing more is needed to deploy the plugin; this limit is the
  trade-off for that.
- **The soft flush of the rewrite rules happens once,** in the site the event
  starts in.

**Proven by:** `tests/php/Fixture/Lifecycle/RewriteLifecycleService.php`,
exercised by `tests/php/Unit/ServiceBasedPluginTest.php`; the scoped
interfaces by `tests/php/Unit/ScopedLifecycleTest.php` and
`tests/php/Unit/SiteLifecycleHooksTest.php`, with an in-memory multisite in
`tests/php/Fixture/Lifecycle/Multisite.php`.

**Rules:** `architecture.compositionRootContracts` runs `activate()` and
`deactivate()`, and the scoped methods, twice and reports a second run that
changes anything, so guard each step or make it set rather than add.

## I need to clean up when the plugin is uninstalled

**What people reach for:** an `uninstall.php` that deletes options with
`delete_option()` in the current site only, or `register_uninstall_hook()` at
the top of the main plugin file with a closure.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns):
the main plugin file is the only file with side effects, and an
`uninstall.php` would be a second one. WordPress stores the uninstall
callback in the database and only accepts a function or a static method, so
it needs a bridge into the object graph
([principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)).
Registering it at include time would write to the database on every request,
and would even add the entry back while WordPress is uninstalling the plugin.
Deleting only in the current site leaves the data of every other site on a
network behind. And [the core idea](design-principles.md#how-services-map-to-it):
each service cleans up its own data.

**Instead:** implement `SiteUninstallable` for what the service keeps in
each site and `NetworkUninstallable` for what it keeps for a network, in a
focused service without a condition. The composition root names the main
plugin file and the uninstall bridge, and the plugin registers the bridge when
it is activated:

```php
// src/BasicScaffoldPlugin.php
protected function get_uninstall_callback(): array {
	return [ BasicScaffoldUninstallHook::class, 'uninstall' ];
}
```

```php
final class ReportsCleanup implements Service, SiteUninstallable, NetworkUninstallable {

	public function uninstall_site( SiteContext $context ): void {
		\delete_option( 'mwpd_basic_scaffold_reports' ); // Fine if it is gone already.
	}

	public function uninstall_network( NetworkContext $context ): void {
		// Switching sites does not switch networks: name the network.
		\delete_network_option( $context->network_id()->to_int(), 'mwpd_basic_scaffold_reports' );
	}
}
```

When the plugin is deleted, WordPress includes its main file and calls the
bridge, which hands over to `ServiceBasedPlugin::uninstall()`. On a single
site, that cleans up the current site. On multisite, it visits every site of
every network, whether or not the plugin was ever active there, then calls
the network cleanup once for each network, in its main site. Each call gets
an object graph of its own, built in the site it runs in, so a `Conditional`
service is asked there. Nothing else is built or registered, even if
`plugins_loaded` fires afterwards, and `WP_UNINSTALL_PLUGIN` plays no part.

Things to know:

- **Keep cleanup unconditional.** A `Conditional` cleanup service only runs
  in the sites where its condition holds in the uninstall request, which is
  rarely what the condition was written for, and data stays behind elsewhere.
  Cleanup that has to happen in any case belongs in its own service without a
  condition, like the one above.
- **Only the plugin's own data, and absence is fine.** Delete prefixed names
  only, and expect that a site never had the data or that an earlier attempt
  already removed it.
- **A failure stops the uninstall, visibly.** The first exception propagates
  after the site context is restored; later sites and networks are not
  visited, and what was cleaned up stays cleaned up. Before the exception
  leaves, the plugin registers its uninstall callback again in the site the
  plugin was deleted in, so deleting it again, once the cause is fixed,
  retries the cleanup from the start.
- **Where the callback is registered.** Activation registers it on the
  activating site and on the main site of every network, because each
  network admin reads it from its main site. A successful uninstall removes
  those copies. A network added later, or a network whose main site changes,
  gets the callback at the next activation, and so does an installation of a
  plugin generated before this existed.
- **Large installations.** The uninstall runs within the request that deletes
  the plugin. Sites are looked up in pages, which bounds memory but not time,
  so a very large installation can run into the time limit. A custom
  `SiteRunner`, for example one driven from WP-CLI, is the answer, as shown in
  [I need to set something up when the plugin is activated](#i-need-to-set-something-up-when-the-plugin-is-activated).

**Proven by:** `src/BasicScaffoldUninstallHook.php:36-38`, the cleanup service
in `tests/php/Fixture/Lifecycle/UnconditionalCleanupService.php`, and
`tests/php/Unit/UninstallLifecycleTest.php` and
`tests/php/Unit/UninstallBridgeTest.php`.

**Rules:** `architecture.pluginFactoryOnlyAtEdges` lists the bridge,
`architecture.bootstrapContract` keeps `register_uninstall_hook()` out of the
main file, and `architecture.compositionRootContracts` runs
`uninstall_site()` and `uninstall_network()` twice and reports a second run
that changes anything.

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

Never bind `Injector` in the composition root's bindings or the plugin bindings
filter: it is reserved, and either binding is rejected with
`InvalidConfiguration`. Services must receive the plugin's own injector. To
use a custom implementation, remove the binding and pass that injector to the
plugin constructor instead:

```php
$plugin = new BasicScaffoldPlugin( true, $custom_injector );
```

**Proven by:** `src/SampleSubsystem/SampleBackendService.php:60-66` and
`:82-99`, `src/Infrastructure/View/TemplatedViewFactory.php:39` and `:90-111`,
bound at `src/BasicScaffoldPlugin.php:130`. The injector is made injectable at
`src/Infrastructure/ServiceBasedPlugin.php:1168-1171`, proven by
`tests/php/Unit/ServiceBasedPluginTest.php:1013-1061`.

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
| Access from a theme or another plugin | [The plugin's hooks](#i-need-a-public-api-for-themes-or-other-plugins) |

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

**Proven by:** `src/BasicScaffoldPlugin.php:212-222` is where shared instances
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
  yet; that is open in #143. The uninstall bridge is the approved exception:
  a failed uninstall propagates, so that it is visible and can be retried.

The scaffold's own bridge outside the main file is the uninstall bridge,
`BasicScaffoldUninstallHook::uninstall()`, which WordPress calls when the
plugin is deleted, as
[I need to clean up when the plugin is uninstalled](#i-need-to-clean-up-when-the-plugin-is-uninstalled)
shows.

**Proven by:** `basic-scaffold.php:129-151`, `src/BasicScaffoldUninstallHook.php:31-39`,
and the factory at `src/BasicScaffoldPluginFactory.php:45-63`.

**Rules:** `architecture.noStaticState` allows static state only in the
factory. [`architecture.pluginFactoryOnlyAtEdges`](architecture-rules.md#architecturepluginfactoryonlyatedges)
reports calls to the factory outside the main file and methods listed in
`parameters.architecture.pluginFactoryOnlyAtEdges.bridges`.

## I need a public API for themes or other plugins

**What people reach for:** a static `MyPlugin::get( 'reports' )`, a file of
global template-tag functions, or telling other developers to call
`BasicScaffoldPluginFactory::create()->get_container()->get( $id )`, because
nothing stops them.

**Why not here:** [principle 13](design-principles.md#13-one-plugin-to-arbitrary-complexity)
makes the plugin's hooks its public integration surface, and
[principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)
gives code outside the plugin no way into the object graph. A static lookup
by identifier is the service locator from the
[principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
table. A typed facade or template-tag functions could avoid that, but each
would be a new public API with its own contract to keep, and none is part of
the supported surface for now. The container door has three problems of its
own:

- **It hands out more than services.** Next to the registered services, the
  container holds the injector that builds their collaborators, under
  `mwpd.basic_scaffold.injector`
  (`src/Infrastructure/ServiceBasedPlugin.php:1174-1177`), so outside code could
  call `bind()`, `share()` or `delegate()` on it, which
  `architecture.noServiceLocator` forbids even the plugin's own subsystems.
- **Whether a service is there depends on the moment.** `get()` throws
  `InvalidService` (`src/Infrastructure/ServiceContainer/SimpleServiceContainer.php:40-42`)
  before `plugins_loaded`, for a `Conditional` service that this request does
  not need, and for a `Delayed` service whose action has not fired yet. None
  of that is visible from the identifier.
- **It returns concrete classes.** Services are `final` classes, so every
  public method on one would become API by accident.

**Instead:** integrate through hooks. There are two kinds:

- **The plugin's configuration filters.** While it registers, the plugin
  applies its `services` filter and the injector filters `bindings`,
  `arguments`, `shared_instances` and `delegations`, each with its hook
  prefix (`mwpd.basic_scaffold.services`, and so on). That happens at
  `plugins_loaded`, once every plugin has been loaded but before the theme
  is, so these filters are for other plugins. A service added this way is
  built by the plugin's injector and receives its collaborators through its
  constructor, like any other service.
- **The hooks your services offer.** When a feature should be open to other
  code, its service applies a prefixed filter or fires a prefixed action at
  the point where it acts, and you document it, including when it fires. A
  hook that fires after the theme has loaded, such as one on `init`, `wp` or
  a rendering hook, serves themes as well as plugins; one that fires during
  registration at `plugins_loaded` comes too early for a theme. Treat what
  comes back from a filter as untrusted input
  ([principle 9](design-principles.md#9-never-trust-what-wordpress-hands-over)).

A theme can also change the plugin's markup without any hook: the views
look in the plugin's own override directory in the child theme, then in the
parent theme, before the plugin
(`src/Infrastructure/View/TemplatedViewFactory.php:24-38`).

```php
// In another plugin, before plugins_loaded: add a service to this plugin.
\add_filter(
	'mwpd.basic_scaffold.services',
	static function ( $services ) {
		if ( is_array( $services ) ) {
			$services['acme.reports.reports-service'] = ReportsService::class;
		}

		return $services; // Anything else is left for the plugin to reject.
	}
);
```

Service identifiers are how these filters name services: they key the
`services` array, and `HasDependencies` lists them. They are not handles for
fetching a service from outside.

**Not supplied, for now.** The plugin offers no other public PHP API: no
static facade, no template-tag functions file, no generic service lookup and
no hook that hands an API object over. `BasicScaffoldPluginFactory::create()`
and `Plugin::get_container()` stay public because the main file, the listed
bridges and the tests need them; that makes them callable, not supported for
other code. #144, which is open and not approved, looks at an API for
extensions that need more than hooks, including the order in which several
extensions register. It waits for a concrete extension that hooks cannot
serve.

Presenters are not an API for other code either. A service passes one with a
render, and only the plugin's own views reach it, including a theme's
overrides of them, never a theme's other files or another plugin. See
[I need a template to ask for more than its context](#i-need-a-template-to-ask-for-more-than-its-context).

**Proven by:** the configuration filters at
`src/Infrastructure/ServiceBasedPlugin.php:1230-1233` and `:1691-1772`, and
`tests/php/Unit/ServiceBasedPluginTest.php:899-921`, where a filter added
after the plugin was constructed, as a plugin loaded later would add it,
takes effect at `plugins_loaded`.

**Rules:** none checks code outside the plugin, so this boundary is held by
documentation. Inside the plugin, `architecture.pluginFactoryOnlyAtEdges`
keeps factory calls to the main file and listed bridges, and
`architecture.prefixedNames` checks that the hooks your services add carry the
plugin's prefix.

## I need WordPress state, such as the current post

**What people reach for:** `global $post;`, `global $wpdb;`, or `get_post()`
deep inside a service. Or a `WP_Post` in the constructor, produced by a
delegation that calls `get_post()`.

**Why not here:** [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
(collaborators pulled from globals) and
[principle 9](design-principles.md#9-never-trust-what-wordpress-hands-over):
what WordPress hands over is checked once, at the boundary. A `WP_Post` in
the constructor keeps both, but it is the wrong post most of the time. The
service is built once, at `wp`, and `the_content` runs many times after
that, and not only for the queried post: for every post in a related-posts
block or a secondary loop, for every post in a feed, and whenever WordPress
builds an excerpt from the content, for example for a meta description. A
post fixed at build time gives each of them the queried post's data.

**Instead:** when the state changes while the request runs, put a small
interface in front of it, and ask that interface each time the callback runs.
Its WordPress implementation is the boundary: it reads WordPress's query
functions when it is asked, checks what they return, and fails with a
dedicated exception when it is not what the service needs. The composition
root binds the interface to that implementation.

```php
// src/SampleSubsystem/MainLoop.php: what the service needs to know.
interface MainLoop {
	public function is_rendering_queried_post(): bool;
	public function queried_post(): WP_Post; // Throws FailedToFindPost.
}
```

```php
// src/SampleSubsystem/WordPressMainLoop.php: the boundary.
public function is_rendering_queried_post(): bool {
	return \in_the_loop()
		&& \is_main_query()
		&& ! \is_feed()
		&& ! \doing_filter( 'get_the_excerpt' )
		&& \get_the_ID() === \get_queried_object_id();
}

public function queried_post(): WP_Post {
	$post = \get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		throw FailedToFindPost::for_main_query();
	}

	return $post;
}
```

```php
// The service only says what it needs, and asks when the filter runs.
public function __construct( ViewFactory $view_factory, MainLoop $main_loop ) { … }

public function prepend_post_header( string $content ): string {
	if ( ! $this->main_loop->is_rendering_queried_post() ) {
		return $content;
	}

	$post = $this->main_loop->queried_post();
	// …
}
```

A test then passes a double of the interface, and needs no WordPress
functions to tell the service which post is being rendered.

A delegation in the composition root still builds a value the injector
cannot, but it runs once, when the service is built. Use it for values that
are fixed for the request, such as where the plugin is
(`ViewConfiguration` in `src/BasicScaffoldPlugin.php`), never for the
current post.

Calling a WordPress function inside an implementation detail is still fine
([principle 4](design-principles.md#4-solid-at-the-top-wordpress-at-the-edges)).
The interface is for state a service depends on.

**Proven by:** `src/SampleSubsystem/MainLoop.php:19-49` and
`src/SampleSubsystem/WordPressMainLoop.php:27-67`, bound at
`src/BasicScaffoldPlugin.php:155`, and asked at call time in
`src/SampleSubsystem/SampleLoopService.php:137-155`.
`tests/php/Unit/WordPressMainLoopTest.php` covers each state of the loop, and
`tests/php/Integration/SampleLoopServiceTest.php:53-115` shows the header on
the queried post only.

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
public function __construct(
	string $path,
	ViewFactory $view_factory,
	?DebugMode $debug_mode = null,
	?ViewConfiguration $configuration = null
) {
	// …
	$this->debug_mode = $debug_mode ?? new WordPressDebugMode();
}
```

**Proven by:** `src/Infrastructure/View/SimpleView.php:95-106`, and the
injector and container defaults of `ServiceBasedPlugin`
(`src/Infrastructure/ServiceBasedPlugin.php:270-302`).

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

Inside a template, `$this` is a `TemplateScope`, not the view: it offers
escaped properties, `isset()` and `empty()`, `raw()` and `render_partial()`,
and nothing else. A context key can therefore use any name, `path` included,
and `$this` gives no access to the view's internals. This guards against
accidents, not against the template: it is trusted PHP code, and reflection
can still get around the scope. Assigning to `$this` fails
the view like any other template error, because the value would skip escaping;
pass what the template needs in the context instead. Annotate templates with
`/** @var TemplateScope $this */` so static analysis knows what `$this` offers.

You do not need a `try`/`catch` around rendering. A view that cannot be
created or rendered throws in debug mode and renders an empty string in
production, so a broken template leaves its own place on the page empty while
the rest of the page renders. See [I need to report a failure](#i-need-to-report-a-failure).

```php
// views/test-backend-service.php
$raw_plugin = $this->raw( 'plugin' );
?>
<p>Hello World! from the <b><?= $this->plugin ?></b> plugin!</p>
<p><em>Raw value: <b><?= esc_html( $raw_plugin ) ?></b></em></p>
```

**Proven by:** `src/SampleSubsystem/SampleBackendService.php:82-99` and
`views/test-backend-service.php:5-15`.

**Rules:** `architecture.outputOnlyThroughViews` requires direct method
calls on typed `Renderable` or `View` objects when printing from services.
`architecture.rawIsEscaped` checks raw values in templates, and PHPCS
(WordPress Coding Standards) checks escaping.

## I need a template to ask for more than its context

**What people reach for:** WordPress or plugin functions called straight from
the template, a global helper or `Plugin::instance()` lookup, or one "helpers"
object that every template can reach.

**Why not here:** [principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)
and [principle 7](design-principles.md#7-show-the-alternative-to-wordpress-anti-patterns)
rule out globals and lookups for collaborators. A plugin-wide helpers object
would also grow with every subsystem and let every template call everything,
which breaks the linear growth the scaffold is built on.

**Instead:** give the template a presenter. Declare what it asks as an
interface that extends `Presenter`, implement it, bind it in the composition
root, inject it into the service that renders the template, and pass it with
the render. Name both types in the template's annotation, so PHPStan and
Psalm check each call:

```php
// The service passes its presenter with the render.
$this->view_factory->create( 'views/test-loop-service' )->render( [], $this->post_header );

// views/test-loop-service.php
/** @var TemplateScope&PostHeader $this */
?>
Post title: <?= $this->title() ?>
```

Use the context for values the service already has, and a presenter for
queries: ones that take arguments, need collaborators, or should be answered
when the template asks.

- Only methods declared on an interface that extends `Presenter` are callable.
  Any other public method of the implementation fails the view.
- A string or stringable result is escaped for HTML text, like a context
  property, and anything else, such as a boolean for an `if`, comes back as it
  is. A presenter that builds markup on purpose returns `SafeHtml`, and is then
  responsible for escaping every part of it. A value printed into a URL, an
  attribute or JavaScript still needs that context's `esc_*` function.
- A partial inherits the presenter, unless `render_partial()` is given its own
  as the third argument. The presenter holds for that render only.
- Calling a method with no presenter, an undeclared method or a presenter that
  throws fails the view: an exception in debug mode, an empty view in
  production. A presenter method named like one of the scope's own methods
  (`raw`, `render_partial`) fails the same way, as templates could never call
  it.
- Arguments are passed by position. Parameters by reference do not work
  through `$this`.
- Theme overrides call presenter methods by name, so renaming one breaks them.

**Proven by:** `src/SampleSubsystem/PostHeader.php:29-48` and
`src/SampleSubsystem/QueriedPostHeader.php:25-62`, bound at
`src/BasicScaffoldPlugin.php:162`, passed at
`src/SampleSubsystem/SampleLoopService.php:151-152` and called in
`views/test-loop-service.php:6-18`. `tests/php/Unit/SimpleViewPresenterTest.php`
covers results, failures and partials.

**Rules:** PHPStan and Psalm check presenter calls in templates against the
`@var` annotation.

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

The view system is where the scaffold does this today. The view catches a
failing template, throws in debug mode and renders nothing in production; the
view factories do the same for a view they cannot create, and hand out an
`EmptyView` in production. An exception from anywhere else, such as a
service's own code in a hook callback or its registration, is not caught yet:
it reaches WordPress whether debug mode is on or off. That boundary is open in
#143.

```php
final class FailedToFindPost extends RuntimeException implements BasicScaffoldException {

	public static function for_main_query(): self {
		return new self(
			'The main query did not ask for a post. A service that needs the queried post has to wait for the main'
			. ' query, for example by implementing Delayed with the "wp" action, be needed only on singular views,'
			. ' and ask for the post only while the main loop renders it.'
		);
	}
}
```

```php
try {
	include $this->path;
} catch ( Throwable $throwable ) {
	// … discard the partial output, then report or degrade.
	if ( $this->is_debug_mode() ) {
		throw FailedToLoadView::from_view_exception( $this->path, $throwable );
	}

	return '';
}
```

**Proven by:** `src/Exception/FailedToFindPost.php:27`, thrown at
`src/SampleSubsystem/WordPressMainLoop.php:62`,
`src/Infrastructure/View/SimpleView.php:135-168` and
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
the plugin. Even the uninstall callback is not registered there: activation
stores it, because WordPress keeps it in the database.

**Proven by:** `basic-scaffold.php:76-157`.

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
Theme overrides of the plugin's views live in a directory of their own,
named by `VIEW_OVERRIDE_DIRECTORY`: a theme overrides `views/settings.php`
with `<theme>/basic-scaffold/views/settings.php`, never with its own
`views/settings.php`.

```php
protected const HOOK_PREFIX = 'mwpd.basic_scaffold.';

protected const VIEW_OVERRIDE_DIRECTORY = 'basic-scaffold';

public const SAMPLE_LOOP_SERVICE_ID = self::SERVICE_PREFIX . 'sample-subsystem.sample-loop-service';
```

Rename `VIEW_OVERRIDE_DIRECTORY` together with the prefixes when you generate
a plugin, usually to its slug. Themes that already override the plugin's
views move their files into that directory: a file directly in the theme's
`views/` folder is no longer picked up.

**Proven by:** `src/BasicScaffoldPlugin.php:326-337`, and
`tests/php/Unit/TemplatedViewFactoryTest.php`, which shows that a bare theme
`views/` file is not selected and that two override directories do not see
each other's files.

**Rules:** `architecture.prefixedNames` covers hooks, stored keys, asset
handles, post types, taxonomies, shortcodes, REST namespaces, cron hooks,
admin pages, settings and AJAX actions. `architecture.serviceIdsPrefixed`
checks declared service identifiers against the composition root's own
`SERVICE_PREFIX`. IDs added by runtime filters and the value of
`VIEW_OVERRIDE_DIRECTORY` are reviewed by hand.

## I need to see why a service did not run

**What people reach for:** `error_log()` calls in `is_needed()` and
`register()`, or a debug panel that reads the service container through a
static accessor and lists what it finds there.

**Why not here:** [principle 10](design-principles.md#10-errors-are-exceptions)
keeps `error_log()` out of `src/`, and
[principle 5](design-principles.md#5-one-composition-root-builds-the-object-graph)
rules out the static accessor. The container would not answer the question
anyway: it holds lazily built services as a wrapper that hides their class,
and it knows nothing about a service that was skipped, or that still waits for
its action or its dependencies. Asking each condition again to find out would
run application code a second time, at another point in the request, and
could get another answer.

**Instead:** declare the optional Query Monitor panel. It is one more entry in
the composition root's services, which ships commented out:

```php
// src/BasicScaffoldPlugin.php, in get_service_classes()
self::REGISTRATION_PANEL_ID => RegistrationPanel::class,
```

The bindings and arguments it needs are already in the composition root, so
nothing else changes. In a request where [Query Monitor](https://wordpress.org/plugins/query-monitor/)
runs, its menu then has a panel named after the plugin. It lists every service
with the class its declaration resolved to and its current state: registered,
skipped, waiting for an action, waiting for dependencies, or failed, with the
action or the dependency identifiers as evidence. Below that is the full
history of the request, one row for each decision, in the order the plugin
made them, so a service that waited for `init` and was then registered shows
both.

How it works, and what it costs:

- **Nothing happens without both the entry and a running Query Monitor.**
  Without the entry, no class of the panel is loaded. With it, the plugin asks
  the panel once, before it schedules any service, whether it observes the
  registration (`ObservesRegistration`). The panel says yes only while Query
  Monitor runs and supports the request, which it checks without loading
  anything of Query Monitor
  (`src/Infrastructure/QueryMonitor/RegistrationPanel.php:240-245`). Otherwise
  the plugin records nothing and creates no log, and the panel is skipped like
  any other `Conditional` service. Query Monitor only tells a Customizer
  preview apart later in the request, so the panel stays off in every request
  that loads the Customizer, its own screen included.
- **The history is recorded as it happens.** While the panel observes, the
  plugin writes each decision into a `RegistrationLog`, request by request and
  in memory only. It costs one small record per decision and nothing is
  stored. Nothing asks a condition or a resolver again, and no lazy service is
  built to be shown. The panel receives the history through the read-only
  `RegistrationReport`; no service can receive the writer.
- **Query Monitor's classes stay behind factories.** The collector and the
  output of the panel extend Query Monitor's classes, so their factories
  declare them as anonymous classes and only create them once Query Monitor
  asks for the panel. The panel is a view,
  `views/query-monitor-registration.php`, escaped like any other; Query
  Monitor prints it on the server and shows it when the panel is opened.
- **Supported release:** Query Monitor 4.0.7. The panel relies on its
  `QueryMonitor` class only being loaded when it runs, on `QM_Collectors::add()`
  and on the `qm/outputter/html` and `qm/output/menus` filters.
- **A custom log** can be passed to the plugin constructor as the last
  optional argument, as in `new BasicScaffoldPlugin( true, null, null, null, null, $log )`.
  It is only written to while a service observes the registration.
- **An override can bypass the record.** `schedule_potential_service_registration()`
  and `maybe_register_service()` are protected and record their decisions; an
  override in a generated plugin that does not call the parent method records
  nothing for the services it handles.

Another service can show the same history: implement `ObservesRegistration`,
return whether it shows it in this request, and ask for a `RegistrationReport`
in its constructor.

**Proven by:** the panel is declared in
`tests/php/Fixture/CompositionRoot/RegistrationPanelPlugin.php` and checked
with the real configuration by `tests/php/Architecture/CompositionRootContractTest.php`;
the recording by `tests/php/Unit/RegistrationRecordingTest.php`; the panel, its
quiet path and both plugin load orders by `tests/php/Unit/RegistrationPanelTest.php`
and `tests/php/Integration/QueryMonitorPanelTest.php`, against stand-ins in
`tests/php/Fixture/QueryMonitor/`.

**Rules:** `architecture.capabilitiesOnServices` keeps `ObservesRegistration`
on services, `architecture.outputOnlyThroughViews` puts the panel's markup in a
view, and the bundled autoloader check loads every class of the panel without
Query Monitor.
