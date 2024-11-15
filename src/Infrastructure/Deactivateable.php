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

/**
 * Something that can be deactivated.
 *
 * By tagging a service with this interface, the system will automatically hook
 * it up to the WordPress deactivation hook.
 *
 * This way, we can just add the simple interface marker and not worry about how
 * to wire up the code to reach that part during the static deactivation hook.
 */
interface Deactivateable {

	/**
	 * Deactivate the service.
	 */
	public function deactivate(): void;
}
