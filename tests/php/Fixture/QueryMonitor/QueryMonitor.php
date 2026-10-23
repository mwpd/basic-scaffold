<?php
/**
 * Stand-in for the QueryMonitor class of Query Monitor 4.0.7.
 *
 * Query Monitor's main file registers its autoloader first and only then
 * decides whether to run. Only when it runs does it call QueryMonitor::init(),
 * which loads this class. So the class being loaded, rather than merely
 * autoloadable, is what tells that Query Monitor runs, and the tests load it
 * only to stand in for a request where it does.
 */

declare( strict_types=1 );

/**
 * classes/QueryMonitor.php: the main plugin class. Query Monitor only loads
 * it, through QueryMonitor::init(), in a request where it runs.
 */
class QueryMonitor {

	/**
	 * classes/QueryMonitor.php:133-149: applies the one-time qm/collectors
	 * filter on plugins_loaded and adds what it returns.
	 *
	 * @return void
	 */
	public function action_plugins_loaded() {
		$collectors = apply_filters( 'qm/collectors', [], $this );

		foreach ( is_array( $collectors ) ? $collectors : [] as $collector ) {
			if ( $collector instanceof QM_Collector ) {
				QM_Collectors::add( $collector );
			}
		}
	}
}
