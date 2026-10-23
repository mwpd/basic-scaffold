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

/**
 * Where the view layer finds the plugin's views.
 *
 * The infrastructure cannot know where the plugin lives: it may sit at any
 * depth below the plugin root, and it must not reach back to the composition
 * root to ask. So the composition root computes the plugin root once, from
 * its own file, and hands it to the view layer in this value object, together
 * with the directory a theme overrides the plugin's views in.
 *
 * The override directory carries the plugin's prefix (design principle 13). A
 * theme overrides `views/settings.php` with
 * `<theme>/<override directory>/views/settings.php`, so two plugins that both
 * have a `views/settings.php`, or a theme with a `views/` folder of its own,
 * cannot override each other's views.
 */
final class ViewConfiguration {

	/**
	 * Absolute path to the plugin root.
	 */
	private string $plugin_root;

	/**
	 * Directory inside a theme that holds the overrides for this plugin's views.
	 */
	private string $override_directory;

	/**
	 * Instantiate a ViewConfiguration object.
	 *
	 * @param string $plugin_root        Path to the plugin root, the directory
	 *                                   relative view names start from.
	 * @param string $override_directory Directory inside a theme that holds the
	 *                                   overrides for this plugin's views. A
	 *                                   single directory name, such as the
	 *                                   plugin's slug.
	 * @throws InvalidViewConfiguration If the plugin root is empty or the
	 *                                  override directory is not a single
	 *                                  directory name.
	 */
	public function __construct( string $plugin_root, string $override_directory ) {
		if ( '' === \rtrim( $plugin_root, '/\\' ) ) {
			throw InvalidViewConfiguration::for_empty_plugin_root();
		}

		/*
		 * An empty name would put the overrides straight into the theme, and
		 * a path such as `../views` could reach the theme's own folders. Both
		 * reintroduce the collisions the override directory exists to avoid.
		 */
		if ( \in_array( $override_directory, [ '', '.', '..' ], true )
			|| false !== \strpbrk( $override_directory, '/\\' ) ) {
			throw InvalidViewConfiguration::for_override_directory( $override_directory );
		}

		$this->plugin_root        = $plugin_root;
		$this->override_directory = $override_directory;
	}

	/**
	 * Get the path to the plugin root.
	 *
	 * @return string Path to the plugin root.
	 */
	public function get_plugin_root(): string {
		return $this->plugin_root;
	}

	/**
	 * Get the directory inside a theme that holds the overrides for this
	 * plugin's views.
	 *
	 * @return string Override directory name.
	 */
	public function get_override_directory(): string {
		return $this->override_directory;
	}

	/**
	 * Get the location inside a theme that overrides this plugin's views.
	 *
	 * @param string $theme_directory Root directory of the theme.
	 *
	 * @return string Override location inside the theme.
	 */
	public function get_theme_override_location( string $theme_directory ): string {
		return \rtrim( $theme_directory, '/\\' ) . '/' . $this->override_directory;
	}
}
