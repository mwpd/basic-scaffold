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

namespace MWPD\BasicScaffold\Infrastructure\QueryMonitor;

use QM_Collector;

/**
 * Create the collector that lists the registration panel in Query Monitor.
 *
 * The collector only gives the panel its identifier: the output reads the
 * registration history itself, when Query Monitor prints its panels, so there
 * is nothing to collect beforehand.
 *
 * The collector's class extends QM_Collector, so it is declared in create(),
 * as an anonymous class, and not in a file of its own. Loading this factory
 * loads nothing of Query Monitor; only creating a collector does, which the
 * panel only does while Query Monitor is running.
 */
final class RegistrationCollectorFactory implements CollectorFactory {

	/**
	 * Identifier of the panel in Query Monitor.
	 */
	private string $panel_id;

	/**
	 * Instantiate a RegistrationCollectorFactory object.
	 *
	 * @param string $panel_id Identifier of the panel in Query Monitor, which
	 *                         carries the plugin's prefix (design principle
	 *                         13). Query Monitor uses it in HTML IDs, so it
	 *                         is a slug, such as "mwpd-basic-scaffold-services".
	 */
	public function __construct( string $panel_id ) {
		$this->panel_id = $panel_id;
	}

	/**
	 * Create the collector of the panel.
	 *
	 * @return QM_Collector Collector of the panel.
	 */
	public function create(): QM_Collector {
		return new class( $this->panel_id ) extends QM_Collector {

			/**
			 * Instantiate the collector.
			 *
			 * @param string $panel_id Identifier of the panel.
			 */
			public function __construct( string $panel_id ) {
				parent::__construct();
				$this->id = $panel_id;
			}
		};
	}
}
