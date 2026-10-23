<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Infrastructure\View;

use MWPD\BasicScaffold\Exception\InvalidViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\Service\WordPressDebugMode;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use Throwable;

/**
 * A factory to create templated views.
 *
 * When no locations are provided, the first create() call resolves and keeps
 * the defaults in this exact order:
 *  1. the plugin's override directory in the child theme
 *  2. the plugin's override directory in the parent theme
 *  3. the plugin root
 *
 * The override directory and the plugin root come from the view
 * configuration. With the scaffold's configuration, a theme overrides
 * `views/settings.php` with `<theme>/basic-scaffold/views/settings.php`. A
 * file in the theme's own `views/` folder is not an override, so that two
 * plugins, or a plugin and a theme, cannot replace each other's views.
 */
final class TemplatedViewFactory implements Service, ViewFactory {

	/**
	 * Array of locations to use; empty until first use when no locations were passed.
	 *
	 * @var array<string>
	 */
	private array $locations;

	/**
	 * Debug mode instance to use.
	 */
	private DebugMode $debug_mode;

	/**
	 * Configuration for the default locations, if one was passed.
	 */
	private ?ViewConfiguration $configuration;

	/**
	 * Instantiate a TemplatedViewFactory object.
	 *
	 * @param array<string>      $locations     Locations to use unchanged, or empty for defaults on first use.
	 * @param ?DebugMode         $debug_mode    Debug mode instance to use, for the factory and the views it
	 *                                          creates. Optional, defaults to WordPressDebugMode.
	 * @param ?ViewConfiguration $configuration Configuration that names the plugin root and the theme
	 *                                          override directory. Only the default locations need it;
	 *                                          the composition root passes it to the factory the
	 *                                          injector builds.
	 */
	public function __construct(
		array $locations = [],
		?DebugMode $debug_mode = null,
		?ViewConfiguration $configuration = null
	) {
		$this->locations     = $locations;
		$this->debug_mode    = $debug_mode ?? new WordPressDebugMode();
		$this->configuration = $configuration;
	}

	/**
	 * Create a new view object for a given relative path.
	 *
	 * Resolve default locations on the first call only, once theme state is needed.
	 *
	 * @param string $relative_path Relative path to create the view for.
	 *
	 * @return View Instantiated view object, or a view that renders nothing if
	 *              the view could not be created outside of debug mode.
	 * @throws Throwable If the view could not be created (in debug mode).
	 */
	public function create( string $relative_path ): View {
		try {
			if ( $this->locations === [] ) {
				$this->locations = $this->get_default_locations( $relative_path );
			}

			return new TemplatedView( $relative_path, $this, $this->locations, $this->debug_mode );
		} catch ( Throwable $throwable ) {
			/*
			 * A view that cannot be created, for example because no location
			 * has its template, fails like one that cannot be rendered: loudly
			 * in debug mode, with the original exception, and with an empty
			 * place on the page in production. The factory returns a View
			 * either way, so the code that renders it is the same in both.
			 */
			if ( $this->debug_mode->is_debug_mode() ) {
				throw $throwable;
			}

			return new EmptyView();
		}
	}

	/**
	 * Get the default locations for the templated view.
	 *
	 * The factory asks for them once, on the first create() call, and keeps them on
	 * the instance. They are not cached across instances, so every factory
	 * reflects the theme that is active when it first creates a view.
	 *
	 * @param string $relative_path Relative path of the view that needs them.
	 *
	 * @return array<string> Array of default locations.
	 * @throws InvalidViewConfiguration If no configuration names the plugin
	 *                                  root and the override directory.
	 */
	private function get_default_locations( string $relative_path ): array {
		if ( null === $this->configuration ) {
			throw InvalidViewConfiguration::for_missing_configuration( $relative_path );
		}

		$plugin_root = $this->configuration->get_plugin_root();

		// We wrap the WP functions here to not make the code directly rely
		// on WordPress being loaded here.
		// This makes the code more flexible and testing easier.
		return ( function_exists( 'get_stylesheet_directory' )
				&& function_exists( 'get_template_directory' ) )
			? [
				$this->configuration->get_theme_override_location( \get_stylesheet_directory() ),
				$this->configuration->get_theme_override_location( \get_template_directory() ),
				$plugin_root,
			]
			: [ $plugin_root ];
	}
}
