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

namespace MWPD\BasicScaffold\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when the view layer is not configured, or not configured
 * correctly, to find the plugin's views.
 */
final class InvalidViewConfiguration extends InvalidArgumentException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for a view that needs the plugin
	 * root, created without a view configuration that names it.
	 *
	 * @param string $path Path of the view as it was requested.
	 */
	public static function for_missing_configuration( string $path ): self {
		$message = \sprintf(
			'The view "%s" is resolved relative to the plugin root, but no view configuration says where that is. '
			. 'Pass a ViewConfiguration to the view or its factory; the composition root does this '
			. 'for every view factory the injector builds.',
			$path
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an empty plugin root.
	 */
	public static function for_empty_plugin_root(): self {
		return new self( 'The plugin root of a view configuration cannot be empty.' );
	}

	/**
	 * Create a new instance of the exception for a theme override directory
	 * that is not a single directory name.
	 *
	 * @param string $directory Override directory that was passed.
	 */
	public static function for_override_directory( string $directory ): self {
		$message = \sprintf(
			'The theme override directory "%s" must be a single directory name, such as the plugin\'s slug, '
			. 'without path separators.',
			$directory
		);

		return new self( $message );
	}
}
