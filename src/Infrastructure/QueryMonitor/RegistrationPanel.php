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

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Registration\ObservesRegistration;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationReport;
use MWPD\BasicScaffold\Infrastructure\Service;
use QM_Collector;
use QM_Collectors;
use QM_Output_Html;

/**
 * Show in Query Monitor how the plugin registered its services in this
 * request, to answer "why didn't this service run?".
 *
 * The panel lists every service the plugin decided about, with the class it
 * resolved to and its current state, and below that the full history of
 * those decisions: which services were registered, which were skipped
 * because they were not needed, which still wait for their registration
 * action, and which wait for, or failed on, which dependencies.
 *
 * The panel is optional, and off unless the composition root declares it
 * among its services. Even then, it only does anything in a request where
 * Query Monitor runs: in any other, it is not needed, the plugin records
 * nothing, and no class that depends on Query Monitor is loaded.
 *
 * Supported release: Query Monitor 4.0.7.
 */
final class RegistrationPanel implements Service, Registerable, Conditional, ObservesRegistration {

	/**
	 * Priority on Query Monitor's filters, after its own panels.
	 */
	private const PRIORITY = 200;

	/**
	 * Registration history to show.
	 */
	private RegistrationReport $report;

	/**
	 * Factory for the collector of the panel.
	 */
	private CollectorFactory $collector_factory;

	/**
	 * Factory for the output of the panel.
	 */
	private OutputFactory $output_factory;

	/**
	 * Collector of the panel, once it is created.
	 */
	private ?QM_Collector $collector = null;

	/**
	 * Output of the panel, once it is created.
	 */
	private ?QM_Output_Html $output = null;

	/**
	 * Instantiate a RegistrationPanel object.
	 *
	 * @param RegistrationReport $report            Registration history to show.
	 * @param CollectorFactory   $collector_factory Factory for the collector of the panel.
	 * @param OutputFactory      $output_factory    Factory for the output of the panel.
	 */
	public function __construct(
		RegistrationReport $report,
		CollectorFactory $collector_factory,
		OutputFactory $output_factory
	) {
		$this->report            = $report;
		$this->collector_factory = $collector_factory;
		$this->output_factory    = $output_factory;
	}

	/**
	 * Check whether the panel is needed: only while Query Monitor runs.
	 *
	 * @return bool Whether Query Monitor runs in this request.
	 */
	public static function is_needed(): bool {
		return self::query_monitor_runs();
	}

	/**
	 * Check whether the panel observes the registration: only while Query
	 * Monitor runs, because nothing would show the record otherwise.
	 *
	 * @return bool Whether Query Monitor runs in this request.
	 */
	public static function observes_registration(): bool {
		return self::query_monitor_runs();
	}

	/**
	 * Hook the panel into Query Monitor.
	 *
	 * The collector joins Query Monitor directly, rather than through its
	 * qm/collectors filter: that filter is applied once, on plugins_loaded,
	 * and whether it runs before or after the plugin registers its services
	 * depends on the order the two plugins are loaded in. Query Monitor
	 * processes its collectors and prints its panels at shutdown, so the
	 * collector joins at the start of shutdown, whichever plugin loaded first.
	 *
	 * Only hooks are added here, with callbacks that stay the same, so that
	 * registering twice changes nothing (design principle 11).
	 */
	public function register(): void {
		\add_action( 'shutdown', [ $this, 'add_collector' ], 0, 0 );
		\add_filter( 'qm/outputter/html', [ $this, 'add_output' ], self::PRIORITY, 1 );
		\add_filter( 'qm/output/menus', [ $this, 'add_menu' ], self::PRIORITY, 1 );
	}

	/**
	 * Add the collector of the panel to Query Monitor.
	 *
	 * Query Monitor keys its collectors by identifier, so adding it again
	 * replaces it.
	 */
	public function add_collector(): void {
		QM_Collectors::add( $this->get_collector() );
	}

	/**
	 * Add the output of the panel to Query Monitor's HTML outputters.
	 *
	 * Query Monitor prints each outputter into a container named after its
	 * key, and opens that container for the menu entry of the same panel, so
	 * the key is the collector's identifier.
	 *
	 * @param mixed $outputters Outputters Query Monitor collected so far.
	 *
	 * @return mixed Outputters with the panel's output added, or what was
	 *               handed over if it is not an array.
	 */
	public function add_output( $outputters ) {
		if ( ! is_array( $outputters ) ) {
			return $outputters;
		}

		$outputters[ $this->get_collector()->id ] = $this->get_output();

		return $outputters;
	}

	/**
	 * Add the menu entry of the panel to Query Monitor's menu.
	 *
	 * @param mixed $menus Menu entries Query Monitor collected so far, keyed
	 *                     by panel.
	 *
	 * @return mixed Menu entries with the panel's entry added, or what was
	 *               handed over if it does not have that shape.
	 */
	public function add_menu( $menus ) {
		if ( ! is_array( $menus ) ) {
			return $menus;
		}

		$entries = [];

		foreach ( $menus as $panel => $entry ) {
			if ( ! is_string( $panel ) || ! is_array( $entry ) ) {
				return $menus;
			}

			$entries[ $panel ] = $entry;
		}

		return $this->get_output()->admin_menu( $entries );
	}

	/**
	 * Get the collector of the panel, creating it the first time.
	 *
	 * @return QM_Collector Collector of the panel.
	 */
	private function get_collector(): QM_Collector {
		if ( null === $this->collector ) {
			$this->collector = $this->collector_factory->create();
		}

		return $this->collector;
	}

	/**
	 * Get the output of the panel, creating it the first time.
	 *
	 * @return QM_Output_Html Output of the panel.
	 */
	private function get_output(): QM_Output_Html {
		if ( null === $this->output ) {
			$this->output = $this->output_factory->create( $this->get_collector(), $this->report );
		}

		return $this->output;
	}

	/**
	 * Check whether Query Monitor runs in this request.
	 *
	 * This is the one place that knows how to tell, checked against Query
	 * Monitor 4.0.7. Its main file loads its autoloader before it decides
	 * whether to run at all, and then returns early when QM_DISABLED is set,
	 * during installation, cron and WP-CLI requests, and during plugin
	 * updates. Only when it runs does it create its QueryMonitor instance,
	 * which loads the QueryMonitor class and, through its built-in
	 * collectors, QM_Collectors. Both are checked without autoloading, so
	 * the check never loads anything of Query Monitor itself: a class that
	 * could merely be autoloaded does not mean Query Monitor runs.
	 *
	 * Query Monitor also stops collecting in requests it does not support,
	 * such as the Customizer preview or an iframed request, which it asks
	 * QM_Collectors::request_supported() about. That is asked too, once the
	 * class is known to be loaded.
	 *
	 * @return bool Whether Query Monitor runs in this request.
	 */
	private static function query_monitor_runs(): bool {
		return \class_exists( 'QueryMonitor', false )
			&& \class_exists( 'QM_Collectors', false )
			&& QM_Collectors::request_supported();
	}
}
