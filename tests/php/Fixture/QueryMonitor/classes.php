<?php
/**
 * Stand-ins for the classes of Query Monitor 4.0.7 that the registration
 * panel uses, other than the QueryMonitor class itself (see QueryMonitor.php).
 *
 * They serve two purposes. PHPStan and Psalm analyse this directory with the
 * tests, which tells them about the Query Monitor classes that the optional
 * integration in src/Infrastructure/QueryMonitor/ names. And the tests that
 * need Query Monitor load them through QueryMonitorInstallation, in a
 * separate process, the way Query Monitor's own autoloader would, so that
 * every other test runs as if Query Monitor were absent. Composer does not
 * autoload them.
 *
 * Only the members the integration and its tests use are here, with the
 * signatures of the release:
 * https://github.com/johnbillion/query-monitor/tree/4.0.7
 * The behaviour is reduced to what the tests observe.
 */

declare( strict_types=1 );

/**
 * classes/Data.php: data transfer object of a collector.
 */
abstract class QM_Data {
}

/**
 * data/fallback.php: data of third-party collectors.
 */
class QM_Data_Fallback extends QM_Data {
}

/**
 * classes/Collector.php: abstract data collector.
 */
abstract class QM_Collector {

	/**
	 * Collected data.
	 *
	 * @var QM_Data
	 */
	protected $data;

	/**
	 * Identifier of the collector.
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Instantiate the collector with fresh storage.
	 */
	public function __construct() {
		$this->data = $this->get_storage();
	}

	/**
	 * Called when the collector is added.
	 *
	 * @return void
	 */
	public function set_up() {}

	/**
	 * Identifier with Query Monitor's prefix, as used in HTML IDs.
	 *
	 * @return string
	 */
	final public function id() {
		return "qm-{$this->id}";
	}

	/**
	 * Collected data.
	 *
	 * @return QM_Data
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Storage for the collected data.
	 *
	 * @return QM_Data
	 */
	public function get_storage(): QM_Data {
		return new QM_Data_Fallback();
	}
}

/**
 * classes/Collectors.php: the container of the collectors.
 */
class QM_Collectors {

	/**
	 * Collectors, by identifier.
	 *
	 * @var array<string, QM_Collector>
	 */
	private $items = [];

	/**
	 * Add a collector, replacing one with the same identifier.
	 *
	 * @param QM_Collector $collector Collector to add.
	 * @return void
	 */
	public static function add( QM_Collector $collector ) {
		$collectors = self::init();

		$collector->set_up();

		$collectors->items[ $collector->id ] = $collector;
	}

	/**
	 * Fetch a collector.
	 *
	 * @param string $id The collector ID.
	 * @return QM_Collector|null The collector object.
	 */
	public static function get( $id ) {
		return self::init()->items[ $id ] ?? null;
	}

	/**
	 * The one container of the request.
	 *
	 * @return self
	 */
	public static function init() {
		/** @var QM_Collectors|null $instance */
		static $instance = null;

		if ( ! $instance ) {
			$instance = new QM_Collectors();
		}

		return $instance;
	}

	/**
	 * Determine whether the current request supports data collection, as the
	 * release does for the checks that do not need WordPress loaded.
	 */
	public static function request_supported(): bool {
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return false;
		}

		// The release also checks that the constant is true, which WordPress
		// always defines it as.
		if ( defined( 'IFRAME_REQUEST' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Query Monitor's own check.
		if ( isset( $_GET['interim-login'] ) && '' !== $_GET['interim-login'] && '0' !== $_GET['interim-login'] ) {
			return false;
		}

		return true;
	}
}

/**
 * classes/Output.php: abstract output handler.
 */
abstract class QM_Output {

	/**
	 * Collector instance.
	 *
	 * @var QM_Collector
	 */
	protected $collector;

	/**
	 * Instantiate the output for a collector.
	 *
	 * @param QM_Collector $collector Collector.
	 */
	public function __construct( QM_Collector $collector ) {
		$this->collector = $collector;
	}

	/**
	 * Output of the panel.
	 *
	 * @return mixed
	 */
	abstract public function get_output();

	/**
	 * Print the output.
	 *
	 * @return void
	 */
	public function output() {
		// nothing
	}

	/**
	 * Collector of the output.
	 *
	 * @return QM_Collector
	 */
	public function get_collector() {
		return $this->collector;
	}
}

/**
 * output/Html.php: abstract output for HTML pages.
 */
abstract class QM_Output_Html extends QM_Output {

	/**
	 * Whether Query Monitor renders the panel in the browser, from the data
	 * of its collector, rather than printing get_output().
	 *
	 * @var bool
	 */
	public static $client_side_rendered = false;

	/**
	 * Name of the panel.
	 *
	 * @return string
	 */
	public function name() {
		return $this->collector->id;
	}

	/**
	 * Add the panel's entry to Query Monitor's menu.
	 *
	 * @param array<string, mixed[]> $menu Menu entries.
	 * @return array<string, mixed[]>
	 */
	public function admin_menu( array $menu ) {
		$menu[ $this->collector->id() ] = $this->menu(
			[
				'title' => $this->name(),
			]
		);

		return $menu;
	}

	/**
	 * Buffer what output() prints.
	 *
	 * @return string
	 */
	public function get_output() {
		ob_start();
		$this->output();

		return (string) ob_get_clean();
	}

	/**
	 * Menu entry of the panel.
	 *
	 * @param array<string, mixed> $args Entry arguments.
	 * @return array<string, mixed>
	 */
	protected function menu( array $args ) {
		return array_merge(
			[
				'id'    => $this->collector->id,
				'panel' => $this->collector->id,
			],
			$args
		);
	}
}
