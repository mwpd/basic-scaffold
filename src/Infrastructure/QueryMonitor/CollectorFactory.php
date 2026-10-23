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
 * Create the Query Monitor collector of the registration panel.
 *
 * Query Monitor lists a panel through a collector, an object of a class that
 * extends its QM_Collector. That class only exists while Query Monitor is
 * loaded, so a factory creates the collector, and only once the panel is
 * registered: a request without Query Monitor never asks for one, and never
 * loads a class that depends on it. Naming QM_Collector as the return type
 * loads nothing.
 */
interface CollectorFactory {

	/**
	 * Create the collector of the panel.
	 *
	 * Only call this while Query Monitor is running.
	 *
	 * @return QM_Collector Collector of the panel.
	 */
	public function create(): QM_Collector;
}
