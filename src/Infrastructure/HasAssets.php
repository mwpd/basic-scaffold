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

namespace MWPD\BasicScaffold\Infrastructure;

use MWPD\BasicScaffold\Infrastructure\Asset\Asset;
use MWPD\BasicScaffold\Infrastructure\Asset\AssetContext;

/**
 * Something that needs scripts or styles.
 *
 * A service declares its assets; it never hooks into the enqueue actions
 * itself. At each of them, the plugin asks every registered service that has
 * assets which ones it needs there, and loads them. A service that is not
 * needed in a request, or not registered yet, is not asked, so Conditional and
 * Delayed already decide most of where an asset loads.
 */
interface HasAssets {

	/**
	 * Get the assets the service needs at an enqueue point.
	 *
	 * The plugin asks at each enqueue action, so the answer can depend on the
	 * state of the request, such as the post being shown. Return nothing to
	 * load nothing there.
	 *
	 * @param AssetContext $context Where in the request the assets are loaded.
	 *
	 * @return iterable<Asset> Assets to load.
	 */
	public function get_assets( AssetContext $context ): iterable;
}
