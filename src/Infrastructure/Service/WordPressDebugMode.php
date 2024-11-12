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

namespace MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A debug mode service that uses the global state of the WordPress constant.
 */
final class WordPressDebugMode implements DebugMode {

	/**
	 * Check if the application is in debug mode.
	 *
	 * @return bool True if debug mode is active, false otherwise.
	 */
	public function is_debug_mode(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}
