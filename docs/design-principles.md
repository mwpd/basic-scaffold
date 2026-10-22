# Design principles

These are the goals every technical decision in this scaffold is measured
against. When a change has more than one reasonable shape, pick the one that
fits these principles. When a change would contradict one, it needs a maintainer
ruling on its issue (`needs: decision`) before any code is written.

Plugins generated from this scaffold inherit the same principles. They are the
reason the infrastructure looks the way it does.

## 1. Runs on PHP and WordPress alone

A plugin built on this scaffold needs nothing on the production server except
PHP and WordPress. There is no `composer install`, no vendor build and no
asset pipeline before it runs: copy the plugin directory and activate it.

Composer is a **development tool**, not a runtime dependency. The scaffold
uses and supports it for tests, static analysis and coding standards, and loads
its autoloader when one is present. When it is absent, the bundled
`Infrastructure\Autoloader` takes over (`basic-scaffold.php:72-86`).

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
  (`src/Infrastructure/ServiceBasedPlugin.php:125-143`). Swapping in an adapter
  for a full-featured container changes one constructor call, not the plugin.
- `ViewFactory` is bound to `TemplatedViewFactory` in the composition root
  (`src/BasicScaffoldPlugin.php:93`) and can be rebound the same way.

When improving infrastructure, keep the simple implementation simple. Add
capability only when the scaffold's own use needs it, and keep the interface
the stable part.

## 3. SOLID at the top, WordPress at the edges

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

## 4. Show the alternative to WordPress anti-patterns

Many patterns that are common throughout the WordPress ecosystem work against
object-oriented design. The scaffold avoids them and, as companion code to a
book, shows the better alternative so that plugins built on it learn it too.
Code and comments here should demonstrate the right way, never the shortcut.

| Common anti-pattern | What the scaffold does instead |
| --- | --- |
| Registering hooks in a constructor, so creating an object changes global state | Constructors only store dependencies. Hooks are added in `register()` (`Registerable`), called by the plugin at the right time (`src/SampleSubsystem/SampleBackendService.php:71-76`). |
| Singletons with `::instance()` and static state | Shared instances come from the injector (`share()`). The plugin factory keeps one shared plugin instance without the Singleton pattern (`basic-scaffold.php:94-106`). |
| Creating collaborators with `new` inside a class, or pulling them from globals | Dependencies are injected through the constructor and configured as bindings in the composition root. |
| `is_admin()` checks wrapped around hook calls throughout the code | A service states when it is needed once, in `Conditional::is_needed()` (`src/SampleSubsystem/SampleBackendService.php:45-51`). |
| Hooking to an early action just to wait for a later one | A service names its registration action through `Delayed`, and the plugin defers it. |
| Doing work when a file is included | Only `basic-scaffold.php` has side effects; everything else is autoloadable declarations. |
| One god class holding the whole plugin | A composition root wires small services, each with one responsibility. |
| Echoing HTML from business logic | Output goes through views from a `ViewFactory`, escaped by default. |

When reviewing a change, an anti-pattern from this list is a defect even if
the code works.

## 5. One plugin, to arbitrary complexity

The scaffold solves everything a single plugin needs, however large that
plugin grows. It deliberately contains no mechanisms for interoperability,
collaboration or synchronization between plugins.

- Hook and service prefixes (`src/BasicScaffoldPlugin.php:182-184`) exist so
  that several plugins built from this scaffold can coexist without clashing.
  They are not a coordination protocol.
- A plugin's filters and service identifiers are its public extension surface
  for other code. That is the extent of cross-plugin support.
- When plugins genuinely need to share infrastructure or coordinate, that
  belongs in a separate, dedicated mechanism or boilerplate, not here.

## 6. Types name concepts, not just data

Types are used for concepts as well as for data structures. When two things
mean different things, they get different types, even if they share the same
underlying data.

In the code:

- `Service` is a pure marker interface with no methods: it exists only to name
  the concept. `ServiceContainer::get()` returns a `Service`
  (`src/Infrastructure/ServiceContainer.php:44`), not just any object.
  `Registerable`, `Conditional`, `Delayed` and the other service interfaces
  each name one capability.
- Every failure has its own exception type (`src/Exception/`), such as
  `FailedToLoadView`, `InvalidService` and `InvalidContextProperty`, rather than
  generic exceptions with different messages.

For new code: prefer a small value object or a dedicated interface over a bare
`int`, `string` or `array` whenever the meaning matters. A post ID and a user ID
are both integers, but mixing them up should be a type error, not a runtime
surprise. The strict static analysis (PHPStan at `max`, Psalm at level 2) is
there to enforce exactly this.

## 7. Never trust what WordPress hands over

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
hook, so `ServiceBasedPlugin` checks each identifier and class name before
using it and throws `InvalidService` on anything unexpected
(`src/Infrastructure/ServiceBasedPlugin.php:221-236`). Bindings, arguments,
shared instances and delegations receive the same treatment after their
filters, raising `InvalidConfiguration`, `InvalidService` or `InvalidArgument`.

The reverse direction gets the same care: values leaving for HTML output go
through `SimpleView`'s escaping unless explicitly requested raw.

## 8. Errors are exceptions

Inside the plugin, every error condition is an exception. The scaffold does not
use WordPress's usual error mechanisms internally: it does not return
`WP_Error` objects, return `false` or `null` to signal a failure, write to the
server log with `error_log()`, or raise notices with `trigger_error()` or
`_doing_it_wrong()`. None of these appear anywhere in `src/`.

WordPress itself still speaks in those terms, so the translation happens at
the boundary, in the same place as principle 7:

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
- **Each specific condition gets its own class.** A new error condition gets a
  dedicated, `final` exception rather than a generic exception with a
  different message.
- **Named constructors build the message.** Exceptions are created through
  static constructors that state the cause, such as
  `FailedToMakeInstance::for_circular_reference()`
  (`src/Exception/FailedToMakeInstance.php:46`) or
  `InvalidService::from_service_id()`. Messages stay consistent, and the
  `Stringify` trait renders arbitrary values safely inside them.

## 9. One composition root builds the object graph

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
  (`src/Infrastructure/ServiceBasedPlugin.php:375-379`). `Conditional` and
  `Delayed` services are not built at all until they are needed.
- **On-demand instantiation through the injector**, when an object can only be
  created later or is needed once per use. Such objects are still produced by
  the injector, or by a factory the injector provides, never by the class that
  needs them. `ViewFactory` is the example: services receive the factory and
  ask it for a view each time they render.

**`new` is reserved for value objects**: small, immutable objects that
represent a value rather than a collaborator, such as an identifier, a
validated path or a date range. Exceptions, created through their named
constructors, fall in the same category. Anything with behaviour that is part
of the application flow (a service, a repository, a view, an adapter around a
WordPress API) is never created with `new` in the class that uses it.
