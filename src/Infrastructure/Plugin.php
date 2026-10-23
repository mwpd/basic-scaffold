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
 * A plugin is basically nothing more than a convention on how manage the
 * lifecycle of a modular piece of code, so that you can:
 *  1. activate it,
 *  2. register it with the framework, and
 *  3. deactivate it again.
 *
 * This is what this interface represents, by assembling the separate,
 * segregated interfaces for each of these lifecycle actions.
 *
 * Additionally, it gives access to the plugin's container, which collects all
 * the services it is made up of. That access is for the plugin's own edges,
 * such as its main file and listed bridges, and for its tests. It is not an
 * API for outside code: the container also holds the injector, and which
 * services it holds depends on the request. Themes and other plugins integrate
 * through the plugin's hooks instead (principle 13).
 */
interface Plugin extends Activateable, Deactivateable, Registerable {

	/**
	 * Get the service container that contains the services that make up the
	 * plugin.
	 *
	 * @return ServiceContainer Service container of the plugin.
	 */
	public function get_container(): ServiceContainer;
}
