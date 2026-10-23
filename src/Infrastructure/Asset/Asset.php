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

namespace MWPD\BasicScaffold\Infrastructure\Asset;

/**
 * A script or style that a service declares for the plugin to load.
 *
 * A declaration is a value: it says what to load, and the plugin decides when
 * and how to hand it to WordPress. The built-in Assets implementation knows
 * Style and Script, and rejects any other implementation of this interface.
 */
interface Asset {

	/**
	 * Get the handle WordPress knows the asset by.
	 *
	 * @return non-empty-string Handle of the asset.
	 */
	public function get_handle(): string;
}
