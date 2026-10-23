# Design principles

These are the goals every technical decision in this scaffold is measured
against. When a change has more than one reasonable shape, pick the one that
fits them. When a change would contradict one, it needs a maintainer ruling on
its issue (`needs: decision`) before any code is written.

Plugins generated from this scaffold inherit the same principles. They are the
reason the infrastructure looks the way it does.

## The core idea: complexity that grows linearly

Code that hooks into WordPress has to act at different points in time, under
different conditions, across several subsystems. Written the usual way, every
new piece of logic has to account for every existing one: nested conditionals
spread through the codebase, and each addition multiplies the combinations that
can go wrong. Complexity grows exponentially.

This scaffold exists to linearize that complexity. Every concern is split off
into its own **service** (or a subsystem of services). Each service decides,
in isolation, where and when it acts within the WordPress lifecycle. The
plugin's infrastructure then works out when to trigger what across all of
them. Adding logic means adding one more service that manages its own
lifecycle, not threading one more condition through all the others.

### What "the WordPress lifecycle" means here

The lifecycle is everything WordPress does between receiving a request and
shutting down, and every point where the plugin API lets code run along the
way:

- **Boot**: WordPress loads its configuration, must-use plugins, then
  network-activated and site-activated plugins (`plugins_loaded`), then the
  theme (`after_setup_theme`), and finishes setting up (`init`, `wp_loaded`).
- **Routing and output**: depending on the request type (front end, admin,
  AJAX, REST, cron or WP-CLI), WordPress parses the request and runs the query
  (`wp`), then renders the response through its template and output hooks
  (`template_redirect`, `wp_head`, `the_content`, `admin_notices`, …).
- **Shutdown**: the `shutdown` action, then PHP ends the request.
- **Plugin lifecycle events**, which happen outside the normal request flow:
  activation and deactivation (including network activation on multisite),
  uninstall, and upgrades.

The same plugin code runs in all of these contexts. Many WordPress conditions
are only meaningful after a certain point: `is_singular()`, for example, only
answers once the query has run. A hook that has already fired does not fire
again. These two facts are what make lifecycle logic hard to get right.

For the full picture, see the Plugin Handbook on
[hooks](https://developer.wordpress.org/plugins/hooks/),
[activation and deactivation](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)
and [uninstall](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/),
and the [action reference](https://developer.wordpress.org/apis/hooks/action-reference/)
for the order of actions in a typical request.

### How services map to it

Each lifecycle question a service has to answer is a small interface in
`src/Infrastructure/`. A service implements only the ones it needs:

| Lifecycle question | Interface | How the plugin uses it |
| --- | --- | --- |
| Is it needed in this request at all? | `Conditional::is_needed()` | Checked before the service is built; if `false`, it never exists. A query-dependent condition also needs `Delayed` to wait for `wp` or later. |
| When does it hook in? | `Delayed::get_registration_action()` | Registration waits for that action, or runs at once if it has already fired. |
| Which services must exist first? | `HasDependencies::get_dependencies()` | Registration waits until those services are registered, and fails with `FailedToResolveDependencies` when they never can be. |
| What does it hook into? | `Registerable::register()` | Called once the service is built, to add its actions and filters. |
| What happens on activation or deactivation? | `Activateable`, `Deactivateable` | Called from the plugin's activation and deactivation hooks. |

The plugin itself only hooks into `plugins_loaded`
(`src/Infrastructure/ServiceBasedPlugin.php:79`) and the activation and
deactivation hooks (`basic-scaffold.php:119-131`). Everything else is decided
per service. `SampleLoopService` shows the result: it declares that it runs at
`wp` and only on singular views
(`src/SampleSubsystem/SampleLoopService.php:55-72`), and no other code has to
know.

The questions are static methods on purpose: the plugin can **decide before it
builds**. A service that is not needed in a request is never instantiated, so
each request only pays for the services it actually uses.

When a service needs a lifecycle point that no interface covers yet, such as
uninstall or a new site on a multisite network, the answer is a new capability
interface in the infrastructure. It is never a conditional inside the service.

## 1. Runs on PHP and WordPress alone

A plugin built on this scaffold needs nothing on the production server except
PHP and WordPress. There is no `composer install`, no vendor build and no
asset pipeline before it runs: copy the plugin directory and activate it.

Composer is a **development tool**, not a runtime dependency. The scaffold
uses and supports it for tests, static analysis and coding standards, and loads
its autoloader when one is present. When it is absent, the bundled
`Infrastructure\Autoloader` takes over (`basic-scaffold.php:74-86`).

In practice:

- `composer.json` has no runtime `require` entries beyond PHP itself. Anything
  in `require-dev` must never be needed at runtime.
- Both autoloading paths must keep working. A change to class loading is
  proven with and without `vendor/`.
- A generated plugin may still choose to add runtime Composer packages. It
  then takes on a build step for itself, as the comment in `basic-scaffold.php`
  notes. The scaffold itself never makes that choice for it.

## 2. Self-contained infrastructure, replaceable by design

The scaffold brings every piece of infrastructure it needs to work: dependency
injection, a service container, view rendering and autoloading. It does not pull
in third-party packages for these common needs. Each one is a deliberately
simple implementation behind an interface, so a stronger third-party
implementation can replace it when a real need appears.

In the code:

- `Injector` is implemented by `SimpleInjector`
  (`src/Infrastructure/Injector/SimpleInjector.php:28`), and `ServiceContainer`
  by `SimpleServiceContainer`
  (`src/Infrastructure/ServiceContainer/SimpleServiceContainer.php:29`).
- `ServiceBasedPlugin` accepts both through its constructor and only falls back
  to the simple versions when none is passed
  (`src/Infrastructure/ServiceBasedPlugin.php:147-166`). Swapping in an adapter
  for a full-featured container changes one constructor call, not the plugin.
- `ViewFactory` is bound to `TemplatedViewFactory` in the composition root
  (`src/BasicScaffoldPlugin.php:95`) and can be rebound the same way.

When improving infrastructure, keep the simple implementation simple. Add
capability only when the scaffold's own use needs it, and keep the interface
the stable part.

## 3. Defaults out of the box (poka-yoke)

The scaffold lets you write the cleanest possible code, but does not force you
to wire everything up before anything works. Where a collaborator has an
obvious default, the default ships built in. Choosing the default needs no
configuration at all, and replacing it is just a matter of passing your own.

The pattern is an optional constructor parameter typed to the interface, with
the built-in implementation as the fallback:

- `ServiceBasedPlugin` accepts any `Injector` and `ServiceContainer`, and falls
  back to `SimpleInjector` and `SimpleServiceContainer`
  (`src/Infrastructure/ServiceBasedPlugin.php:147-166`). The code calls this a
  poka-yoke: a design that makes the easy path also the correct one.
- `SimpleView` accepts any `DebugMode` and falls back to `WordPressDebugMode`
  (`src/Infrastructure/View/SimpleView.php:76`).

The fallback is the only place the default is named. Callers never have to
repeat it, and swapping it never requires touching the class.

Not every implementation is a replacement point. `TemplatedView` layers
WordPress core's theme-override behaviour (child theme, then parent theme,
then plugin) on top of `SimpleView`. That behaviour is fixed on the WordPress
side, so `TemplatedView` is meant to be used or not used, not replaced or
reconfigured. Changes to rendering itself belong in `SimpleView`, and
`TemplatedView` picks them up.

## 4. SOLID at the top, WordPress at the edges

The scaffold aims for the best compromise between well-structured
object-oriented code and code that works without friction in WordPress. It is a
WordPress project, not a Symfony project in WordPress clothing. That compromise
has a clear line in it.

**The high-level design follows SOLID strictly.** This covers the
infrastructure, the composition root, service boundaries and every interface
another part of the plugin depends on:

- **Single responsibility:** each service does one thing. The plugin class only
  wires services together; it does not implement features.
- **Open/closed:** features are added as new services and bindings in the
  composition root, not by editing existing services.
- **Liskov substitution:** any implementation of an interface (`Injector`,
  `ServiceContainer`, `ViewFactory`, `DebugMode`) can stand in for another
  without its callers noticing.
- **Interface segregation:** small capability interfaces (`Registerable`,
  `Conditional`, `Delayed`, `HasDependencies`, `Activateable`,
  `Deactivateable`) that a service implements only when it needs them.
- **Dependency inversion:** high-level code depends on interfaces, and the
  injector supplies the implementations. `SampleBackendService` receives a
  `ViewFactory` through its constructor
  (`src/SampleSubsystem/SampleBackendService.php:60`) and never creates one.

**Implementation details may take shortcuts.** Inside a class, behind its
interface, it is fine to call WordPress functions, use the plugin API and read
WordPress state directly. `WordPressDebugMode` reads `WP_DEBUG` directly
(`src/Infrastructure/Service/WordPressDebugMode.php:26-27`) behind the
`DebugMode` interface the rest of the code depends on. Hooks are the
integration mechanism: use actions and filters rather than building a parallel
event system over them.

Rule of thumb: introduce an abstraction where something higher up needs to be
decoupled from WordPress, for testability or because a second implementation
is plausible. Otherwise, call WordPress.

## 5. One composition root builds the object graph

The plugin's objects are assembled as one tree from a single composition
root. `BasicScaffoldPlugin` (`src/BasicScaffoldPlugin.php`) declares the
services, bindings, arguments, shared instances and delegations, and
`ServiceBasedPlugin` turns that configuration into running services through
the injector. Every object that takes part in the application flow is obtained
from there, directly or indirectly, through constructor injection.

There are exactly two ways to defer part of that tree:

- **Lazy instantiation**, for performance or to break a circular dependency.
  A service that does not need to hook into WordPress is stored as a
  `LazilyInstantiatedService` and only built when first retrieved
  (`src/Infrastructure/ServiceBasedPlugin.php:746-750`). `Conditional` and
  `Delayed` services are not built at all until they are needed.
- **On-demand instantiation through the injector**, when an object can only be
  created later or is needed once per use. Such objects are still produced by
  the injector, or by a factory the injector provides, never by the class that
  needs them. `ViewFactory` is the example: services receive the factory and
  ask it for a view each time they render.

**`new` is reserved for three cases:**

- **Value objects**: small, immutable objects that represent a value rather
  than a collaborator, such as an identifier, a validated path or a date
  range. Exceptions, created through their named constructors, fall in the
  same category.
- **Factories**: a factory's whole job is instantiation, and its internals are
  encapsulated, so it may create what it produces with `new`
  (`src/Infrastructure/View/TemplatedViewFactory.php:59`). Going through the
  injector instead is allowed but not required: the factory is assumed to
  know best how to build its product. The same goes for managing instances:
  `BasicScaffoldPluginFactory` keeps the one plugin instance in a static
  variable (`src/BasicScaffoldPluginFactory.php:38-52`). That works around a
  limitation of WordPress core, which needs static access to the plugin from
  outside the object graph, for example for the activation hooks. Because the
  state is encapsulated in the factory, it does not leak into the rest of the
  code. Static state anywhere else is not acceptable.
- **Poka-yoke defaults**: an optional constructor dependency that falls back to
  the built-in implementation when none is passed (principle 3).

Anything else with behaviour that is part of the application flow (a service,
a repository, a view, an adapter around a WordPress API) is never created with
`new` in the class that uses it.

### Reaching the object graph from outside it

Some code needs a service but is not part of the graph: a callback that
WordPress only accepts as a static method, such as the uninstall hook, or the
activation hooks in the main plugin file. The answer is never a service
locator. It depends on which side of the graph's edge the code is on:

- **Inside the graph**, a class receives what it needs through its
  constructor. When it needs an object later or once per use, it receives a
  factory, or the injector (`src/Infrastructure/ServiceBasedPlugin.php:322-325`),
  and calls only `make()` on it. When it has to
  choose between implementations at runtime, it receives a typed registry for
  that one concept. It never looks a service up in the container.
- **At the edge**, where WordPress calls in from outside, a listed *bridge*
  gets the plugin through the plugin factory and delegates at once to instance
  methods, as the activation hooks do (`basic-scaffold.php:119-131`). A bridge
  holds no state and no logic of its own, and it is where exceptions have to
  be caught before control goes back to WordPress (principle 10); the
  activation hooks do not do that yet (#27).

The [patterns](patterns.md#i-need-a-service-locator) show each case with
code.

## 6. Final by default

Concrete classes are `final`. Variation happens through interfaces and
injected collaborators, not through inheritance. Only classes deliberately
designed as a base are open: `ServiceBasedPlugin` is abstract so the
composition root can extend it (`src/Infrastructure/ServiceBasedPlugin.php:28`),
and `SimpleView` is the base that `TemplatedView` builds on
(`src/Infrastructure/View/SimpleView.php:34`). Every other class in `src/` is
`final`, and new classes follow suit unless they are designed for extension
from the start.

## 7. Show the alternative to WordPress anti-patterns

Many patterns that are common throughout the WordPress ecosystem work against
object-oriented design. The scaffold avoids them and, as companion code to a
book, shows the better alternative so that plugins built on it learn it too.

| Common anti-pattern | What the scaffold does instead |
| --- | --- |
| Registering hooks in a constructor, so creating an object changes global state | Constructors only store dependencies. Hooks are added in `register()` (`Registerable`), called by the plugin at the right time (`src/SampleSubsystem/SampleBackendService.php:71-77`). [Pattern](patterns.md#i-need-to-add-hooks) |
| Singletons with `::instance()` and static state | Shared instances come from the injector (`share()`). The plugin factory keeps one shared plugin instance without the Singleton pattern (`src/BasicScaffoldPluginFactory.php:38-56`). [Pattern](patterns.md#i-need-a-singleton) |
| A service locator, such as a static `Services::get( 'id' )` | Each need behind it has its own answer: constructor injection, a factory, a typed registry, `share()`, or a bridge at the edge of the object graph (principle 5). [Pattern](patterns.md#i-need-a-service-locator) |
| A global variable or singleton so that WordPress callbacks can reach the plugin | A bridge gets the plugin through the plugin factory and delegates at once (`basic-scaffold.php:119-131`). [Pattern](patterns.md#i-need-to-reach-the-plugin-from-a-static-wordpress-callback) |
| Creating collaborators with `new` inside a class, or pulling them from globals | Dependencies are injected through the constructor and configured as bindings in the composition root. [Pattern: creating objects](patterns.md#i-need-to-create-objects-on-demand), [WordPress state](patterns.md#i-need-wordpress-state-such-as-the-current-post) |
| `is_admin()` checks wrapped around hook calls throughout the code | A service states when it is needed once, in `Conditional::is_needed()` (`src/SampleSubsystem/SampleBackendService.php:45-52`). [Pattern](patterns.md#i-need-a-service-only-in-some-requests) |
| Hooking to an early action just to wait for a later one | A service names its registration action through `Delayed`, and the plugin defers it. [Pattern](patterns.md#i-need-to-hook-in-later) |
| Asking query-dependent conditions before the query has run | A `Conditional` service also implements `Delayed` and returns `wp` or a later action from `get_registration_action()`, so the plugin waits before asking `is_needed()`. [Pattern](patterns.md#i-need-to-hook-in-later) |
| Doing work when a file is included | Only `basic-scaffold.php` has side effects; everything else is autoloadable declarations. [Pattern](patterns.md#i-need-to-run-code-when-the-plugin-file-loads) |
| One god class holding the whole plugin | A composition root wires small services, each with one responsibility. [Pattern](patterns.md#i-need-to-add-a-feature) |
| Echoing HTML from business logic | Output goes through views from a `ViewFactory`, escaped by default. [Pattern](patterns.md#i-need-to-print-output) |

When reviewing a change, an anti-pattern from this list is a defect even if
the code works.

## 8. Types name concepts, not just data

Types are used for concepts as well as for data structures. When two things
mean different things, they get different types, even if they share the same
underlying data.

In the code:

- `Service` is a pure marker interface with no methods: it exists only to name
  the concept. `ServiceContainer::get()` returns a `Service`
  (`src/Infrastructure/ServiceContainer.php:44`), not just any object.
  `Registerable`, `Conditional`, `Delayed` and the other service interfaces
  each name one capability.
- Failures are typed by category (`src/Exception/`), such as
  `FailedToLoadView`, `InvalidService` and `InvalidContextProperty`, rather than
  generic exceptions with different messages.

For new code: prefer a small value object or a dedicated interface over a bare
`int`, `string` or `array` whenever the meaning matters. A post ID and a user ID
are both integers, but mixing them up should be a type error, not a runtime
surprise. The strict static analysis (PHPStan at `max`, Psalm at level 2) is
there to enforce exactly this.

## 9. Never trust what WordPress hands over

WordPress data is loosely typed: options, post meta, filter return values,
request parameters, globals and query results may hold anything, whatever their
documentation claims. The scaffold makes no assumptions about such input.

At every boundary where data comes from WordPress:

1. **Sanitize, validate and normalize** it as fully as possible.
2. **Transform** it into an internal type, failing loudly with a specific
   exception when that is impossible.
3. From then on, internal code relies on the internal type and does not
   re-check it.

In the code: the service list passes through a filter that any plugin can
hook, so `ServiceBasedPlugin` checks that it is an array, and that each entry
has a string identifier and names an existing class implementing `Service`,
before using it. It throws `InvalidConfiguration` or `InvalidService` on
anything unexpected (`src/Infrastructure/ServiceBasedPlugin.php:348-389`).
Bindings, arguments, shared instances and delegations receive the same
treatment after their filters, raising `InvalidConfiguration`, `InvalidService`
or `InvalidArgument`. A binding must point to a subtype of what it binds,
whenever both can be loaded (`:813-819`), and the injector checks that a
delegation returns an instance of the class it was registered for
(`src/Infrastructure/Injector/SimpleInjector.php:498-507`).

**Output is escaped for the context it lands in.** The reverse direction gets
the same care. Values a view reads as properties are escaped by default
(`src/Infrastructure/View/SimpleView.php:234-254`). A value fetched with
`raw()` is the explicit exception, and the template must then escape it for
where it is printed: HTML text, an attribute, a URL or JavaScript each need
their own escaping, using WordPress's matching `esc_*` function.

## 10. Errors are exceptions

Inside the plugin, every error condition is an exception. The scaffold does not
use WordPress's usual error mechanisms internally: it does not return
`WP_Error` objects, return `false` or `null` to signal a failure, write to the
server log with `error_log()`, or raise notices with `trigger_error()` or
`_doing_it_wrong()`. None of these appear anywhere in `src/`.

WordPress itself still speaks in those terms, so the translation happens at
the boundary, in the same place as principle 9:

- **Coming in:** a `WP_Error` or a falsy failure value returned by a WordPress
  function is turned into an exception right where the call is made.
- **Going out:** where WordPress expects a `WP_Error` (a REST callback, for
  example) or a user-facing message, the edge code catches the exception and
  converts it. Internal code never does that conversion itself.

The exceptions follow a fixed shape (`src/Exception/`):

- **They keep PHP's own hierarchy.** Each exception extends the SPL exception
  that matches its category: `InvalidArgumentException` for invalid input or
  configuration (`InvalidService`, `InvalidConfiguration`, `InvalidPath`), and
  `RuntimeException` for failures while running (`FailedToMakeInstance`,
  `FailedToLoadView`, `FailedToEscapeValue`). Callers can catch by category
  without knowing the scaffold's classes.
- **They share one marker interface.** Every exception implements
  `BasicScaffoldException` (`src/Exception/BasicScaffoldException.php:25`), so
  everything this plugin throws can be caught in one place.
- **Each kind of failure gets its own class.** A new kind of failure gets a
  dedicated, `final` exception rather than a generic exception with a
  different message. Related conditions within one kind share that class
  through separate named constructors, and get their own class once callers
  need to tell them apart.
- **Named constructors build the message.** Exceptions are created through
  static constructors that state the cause, such as
  `FailedToMakeInstance::for_circular_reference()`
  (`src/Exception/FailedToMakeInstance.php:46`) or
  `InvalidService::from_service_id()`. Messages stay consistent, and the
  `Stringify` trait renders arbitrary values safely inside them.
- **They keep their cause.** When a lower-level failure is translated, the
  original is passed on as the previous exception, as `FailedToLoadView` does
  (`src/Exception/FailedToLoadView.php:39`).

**Exceptions never reach the end user.** They are for internal communication
and for developers. At the point where control is handed back to WordPress (a
hook callback, a rendered view, a REST response), the exception is caught and
the code falls back to how WordPress works:

- **With debug mode on** (`DebugMode`, backed by `WP_DEBUG`), problems surface
  immediately and loudly, so a developer sees what went wrong and where.
- **With debug mode off**, the broken part degrades gracefully and the rest of
  the site keeps working. A view that fails renders nothing. That field or
  notice is still broken, but the page around it is not taken down with it.

This is the same compromise as principle 4: clean, strict code inside, and the
WordPress way at the edges, where forcing the clean approach would create more
problems than it solves.

## 11. Lifecycle steps are idempotent

Registering, activating and deactivating are safe to run more than once.
WordPress can reach the same step through more than one path: activation, for
example, registers the services itself because `plugins_loaded` has already
fired in that request. So the plugin skips registration when it has already
happened (`src/Infrastructure/ServiceBasedPlugin.php:299-302`), and never
registers the same service twice (`src/Infrastructure/ServiceBasedPlugin.php:701-704`).
A step that runs a second time must leave the plugin exactly as the first run
did.

## 12. Extension points run at a hook, never at include time

Filters that let other code change the plugin, such as its services or its
injector configuration, are applied on a WordPress action, never while the
plugin file is being included. At include time, plugins loaded after this one
have had no chance to add their callbacks, so an extension point that runs
then only works for code that happens to load earlier. So the plugin applies
its filters during registration at `plugins_loaded`: the `services` filter
(`src/Infrastructure/ServiceBasedPlugin.php:348-351`), and the injector
filters, because the injector is configured there and not in the constructor
(`src/Infrastructure/ServiceBasedPlugin.php:312`).

## 13. One plugin, to arbitrary complexity

The scaffold solves everything a single plugin needs, however large that
plugin grows. It deliberately contains no mechanisms for interoperability,
collaboration or synchronization between plugins.

- A plugin's filters and service identifiers are its public extension surface
  for other code. That is the extent of cross-plugin support.
- When plugins genuinely need to share infrastructure or coordinate, that
  belongs in a separate, dedicated mechanism or boilerplate, not here.

**Everything public carries the plugin's prefix**, so that several plugins
built from this scaffold can run side by side without clashing. That covers
hook names and service identifiers (`src/BasicScaffoldPlugin.php:206-208`), as
well as option, meta and transient names, script and style handles, and the
paths a theme uses to override the plugin's views. The prefixes prevent
collisions; they are not a coordination protocol.

## 14. The code teaches

This scaffold is companion code to a book, and the plugins generated from it
copy what it does. Its code and comments are teaching material:

- **Comments explain why**, not just what. The bootstrap is split into three
  numbered steps that say what each one is for (`basic-scaffold.php:33-46`),
  and the composition root is laid out the same way
  (`src/BasicScaffoldPlugin.php`, sections 1 to 3).
- **The samples must be correct.** Sample services and views are copied more
  than anything else, so they demonstrate the full pattern, including escaping
  and error handling, and they are covered by tests. A shortcut in a sample is
  a defect, even when it is "only an example".
