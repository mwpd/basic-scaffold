# MWPD Basic Plugin Scaffold

A starting point for WordPress plugins with a clean object-oriented architecture,
written as the companion code to the *Modern WordPress Plugin Development* book.

Plugins hook into WordPress at many points in time, under many conditions. Written
the usual way, every new feature has to account for every existing one, and the
nested conditionals grow with each addition. This scaffold keeps that complexity
linear: every concern is its own **service**, and each service declares where and
when it acts in the WordPress lifecycle through small interfaces. The plugin
infrastructure works out when to trigger what.

```php
final class SampleLoopService implements Service, Registerable, Conditional, Delayed {

	use DefaultRegistrationPriority;   // Registers at priority 10 on its action.

	public static function is_needed(): bool {
		return \is_singular();             // Only on singular views…
	}

	public static function get_registration_action(): string {
		return 'wp';                       // …and only once the query has run.
	}

	public function register(): void {
		\add_filter( 'the_content', [ $this, 'prepend_post_header' ] );
	}

	// …
}
```

## Used in production

The architecture in this scaffold powers two of Google's WordPress plugins. Each
keeps its own adapted copy of the infrastructure, and fixes proven there flow back
into this scaffold.

| Plugin | Active installs | Infrastructure |
| --- | --- | --- |
| [AMP](https://wordpress.org/plugins/amp/): the official AMP plugin for WordPress | ![AMP active installs](https://img.shields.io/wordpress/plugin/installs/amp) | [`src/Infrastructure`](https://github.com/ampproject/amp-wp/tree/develop/src/Infrastructure) |
| [Web Stories](https://wordpress.org/plugins/web-stories/): Google's visual storytelling plugin | ![Web Stories active installs](https://img.shields.io/wordpress/plugin/installs/web-stories) | [`includes/Infrastructure`](https://github.com/GoogleForCreators/web-stories-wp/tree/main/includes/Infrastructure) |

## What you get

- **Services with a declared lifecycle**: `Registerable`, `Conditional`, `Delayed`,
  `HasDependencies`, `Activateable` and `Deactivateable` let each service state when
  it is needed and what it hooks into. Services that a request does not need are
  never even instantiated.
- **Dependency injection** through a single composition root,
  [`src/BasicScaffoldPlugin.php`](src/BasicScaffoldPlugin.php), with a simple
  built-in injector and service container that you can swap for your own.
- **Views** with escaping by default and child-theme and parent-theme overrides.
- **No runtime dependencies.** The plugin needs only PHP and WordPress on the
  server. Composer is used for development tooling, and a bundled autoloader takes
  over when `vendor/` is absent.
- **Strict tooling**: PHPUnit, PHPStan at `max`, Psalm, the WordPress Coding
  Standards and PHPCompatibility, all run in CI.

The reasoning behind all of this is written down in the
[design principles](docs/design-principles.md).

## Requirements

- PHP 7.4 or later
- WordPress 6.0 or later

## Getting started

1. Create your own repository with **Use this template** on GitHub, or
   `gh repo create my-plugin --template mwpd/basic-scaffold`.
2. Make it yours. Rename:
   - the plugin header and file name in `basic-scaffold.php`, including the text
     domain;
   - the `MWPD\BasicScaffold` namespace in `src/`, `tests/` and the `autoload`
     sections of `composer.json`, and the package `name`;
   - the namespaces under `parameters.architecture` in `phpstan.neon.dist`, so
     the [architecture rules](docs/architecture-rules.md) check your code;
   - `BasicScaffoldPlugin`, `BasicScaffoldPluginFactory` and
     `BasicScaffoldException`;
   - the `HOOK_PREFIX` and `SERVICE_PREFIX` constants in
     `src/BasicScaffoldPlugin.php`, so your hooks and service identifiers don't
     clash with other plugins built on this scaffold.
3. Replace the sample services in `src/SampleSubsystem/` and the views in
   `views/` with your own, and list your services in
   `BasicScaffoldPlugin::get_service_classes()`.

## Development

```sh
composer install
composer test   # unit, integration and architecture tests
composer lint   # PHPCS, PHPStan and Psalm, plus the PHPStan rule tests
```

The architecture rules check that new code follows the
[design principles](docs/design-principles.md), and say what to do instead
when it does not. [docs/architecture-rules.md](docs/architecture-rules.md)
lists them.

Psalm 5 supports PHP up to 8.3, so run the lint step on PHP 8.3. CI
(`.github/workflows/ci.yml`) is the reference environment.

## Contributing

Work is planned and tracked in [GitHub issues](https://github.com/mwpd/basic-scaffold/issues),
including work done by coding agents. [`docs/process/github.md`](docs/process/github.md)
describes the labels and the lifecycle of an issue, and [`AGENTS.md`](AGENTS.md)
has the rules every change follows.

## License

[MIT](LICENSE)
