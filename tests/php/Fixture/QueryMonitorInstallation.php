<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use QueryMonitor;

/**
 * A Query Monitor installation for the tests, made of the stand-ins in
 * tests/php/Fixture/QueryMonitor/.
 *
 * It goes through the same steps as Query Monitor's main file: install()
 * registers an autoloader for its classes, which the main file does before it
 * decides whether to run, and run() is what happens when it does run. A
 * request where Query Monitor is disabled, or skips cron or WP-CLI, stops
 * after install().
 *
 * The autoloader serves every stand-in, the main QueryMonitor class
 * included, as Query Monitor's own does, and records each class it is asked
 * for, so a test can tell whether anything asked for a Query Monitor class.
 * Tests that use this run in a separate process, because a loaded class
 * cannot be unloaded.
 */
final class QueryMonitorInstallation {

	/**
	 * Classes the autoloader serves.
	 */
	private const CLASSES = [
		'QM_Data',
		'QM_Data_Fallback',
		'QM_Collector',
		'QM_Collectors',
		'QM_Output',
		'QM_Output_Html',
	];

	/**
	 * Classes the autoloader was asked for, in order.
	 *
	 * @var list<string>
	 */
	private array $requested = [];

	/**
	 * Register Query Monitor's autoloader, as its main file does first.
	 */
	public function install(): self {
		spl_autoload_register( [ $this, 'autoload' ] );

		return $this;
	}

	/**
	 * Run Query Monitor, as its main file does when the request is one it
	 * runs in: QueryMonitor::init() loads the main class, and the built-in
	 * collectors it includes load QM_Collectors.
	 */
	public function run(): QueryMonitor {
		require_once __DIR__ . '/QueryMonitor/QueryMonitor.php';
		class_exists( 'QM_Collectors' );

		return new QueryMonitor();
	}

	/**
	 * Load a Query Monitor class, and record that it was asked for.
	 *
	 * @param string $class_name Class asked for.
	 */
	public function autoload( string $class_name ): void {
		if ( 'QueryMonitor' === $class_name || in_array( $class_name, self::CLASSES, true ) ) {
			$this->requested[] = $class_name;
		}

		if ( in_array( $class_name, self::CLASSES, true ) ) {
			require_once __DIR__ . '/QueryMonitor/classes.php';
		}

		// Query Monitor's autoloader serves its main class too, whether it
		// runs or not. That is why a check must not autoload it.
		if ( 'QueryMonitor' === $class_name ) {
			require_once __DIR__ . '/QueryMonitor/QueryMonitor.php';
		}
	}

	/**
	 * Get the Query Monitor classes the autoloader was asked for.
	 *
	 * @return list<string> Class names, in order.
	 */
	public function get_requested(): array {
		return $this->requested;
	}
}
