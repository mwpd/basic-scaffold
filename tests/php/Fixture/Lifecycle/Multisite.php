<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use Brain\Monkey\Functions;
use LogicException;

/**
 * An in-memory WordPress installation, single site or multisite, for the
 * lifecycle tests.
 *
 * It models what the lifecycle dispatch relies on: sites and networks, the
 * stack of switched sites, options per site and per network, the active
 * plugins and the uninstall callbacks, and site and network queries with
 * paging. install() hands its functions to Brain Monkey.
 *
 * As in WordPress, switching sites does not switch the network the request
 * belongs to, and a site that is switched to more than once stays on the
 * stack until each switch is restored.
 */
final class Multisite {

	public const MAIN_FILE = '/wordpress/wp-content/plugins/basic-scaffold/basic-scaffold.php';

	public const BASENAME = 'basic-scaffold/basic-scaffold.php';

	private bool $multisite;

	/**
	 * Network of each site, in the order of their IDs.
	 *
	 * @var array<int,int>
	 */
	private array $sites = [];

	/**
	 * Main site of each network, in the order of their IDs.
	 *
	 * @var array<int,int>
	 */
	private array $networks = [];

	/**
	 * Sites that core did not manage to set up.
	 *
	 * @var array<int,true>
	 */
	private array $uninitialized = [];

	private int $current;

	private int $request_network;

	/**
	 * Sites switched away from, most recent last.
	 *
	 * @var list<int>
	 */
	private array $stack = [];

	/**
	 * Options of each site.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $options = [];

	/**
	 * Options of each network.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $network_options = [];

	/**
	 * Every switch_to_blog() call, by the site switched to.
	 *
	 * @var list<int>
	 */
	private array $switches = [];

	/**
	 * Every page of sites or networks asked for: kind, network and offset.
	 *
	 * @var list<string>
	 */
	private array $queries = [];

	/**
	 * Every option write, as "site:option".
	 *
	 * @var list<string>
	 */
	private array $writes = [];

	/**
	 * Recorder that also receives each option write, if any.
	 */
	private ?LifecycleRecorder $recorder = null;

	private function __construct( bool $multisite, int $current, int $request_network ) {
		$this->multisite       = $multisite;
		$this->current         = $current;
		$this->request_network = $request_network;
	}

	/**
	 * A single site installation, with site and network 1.
	 */
	public static function single_site(): self {
		$installation              = new self( false, 1, 1 );
		$installation->sites[1]    = 1;
		$installation->networks[1] = 1;
		$installation->options[1]  = [];

		return $installation;
	}

	/**
	 * A multisite installation.
	 *
	 * @param array<int,list<int>> $networks Site IDs of each network, its main site first.
	 * @param int                  $current  Site the request runs in.
	 */
	public static function with_networks( array $networks, int $current ): self {
		$network_of_current = 0;
		foreach ( $networks as $network => $sites ) {
			if ( in_array( $current, $sites, true ) ) {
				$network_of_current = $network;
			}
		}

		$installation = new self( true, $current, $network_of_current );

		foreach ( $networks as $network => $sites ) {
			$installation->networks[ $network ]        = $sites[0];
			$installation->network_options[ $network ] = [];

			foreach ( $sites as $site ) {
				$installation->sites[ $site ]   = $network;
				$installation->options[ $site ] = [];
			}
		}

		ksort( $installation->sites );
		ksort( $installation->networks );

		return $installation;
	}

	/**
	 * Hand the installation's functions to Brain Monkey.
	 */
	public function install(): void {
		Functions\when( 'is_multisite' )->alias( fn(): bool => $this->multisite );
		Functions\when( 'get_current_blog_id' )->alias( fn(): int => $this->current );
		Functions\when( 'get_current_network_id' )->alias( fn(): int => $this->request_network );
		Functions\when( 'plugin_basename' )->alias(
			static fn( string $file ): string => basename( dirname( $file ) ) . '/' . basename( $file )
		);
		Functions\when( 'switch_to_blog' )->alias( [ $this, 'switch_to_blog' ] );
		Functions\when( 'restore_current_blog' )->alias( [ $this, 'restore_current_blog' ] );
		Functions\when( 'get_sites' )->alias( [ $this, 'get_sites' ] );
		Functions\when( 'get_networks' )->alias( [ $this, 'get_networks' ] );
		Functions\when( 'get_main_site_id' )->alias(
			fn( ?int $network = null ): int => $this->networks[ $network ?? $this->request_network ]
		);
		Functions\when( 'wp_is_site_initialized' )->alias(
			fn( int $site ): bool => isset( $this->sites[ $site ] ) && ! isset( $this->uninitialized[ $site ] )
		);
		Functions\when( 'get_option' )->alias(
			/**
			 * @param mixed $default_value Value if the option is missing.
			 * @return mixed
			 */
			fn( string $option, $default_value = false ) =>
				$this->options[ $this->current ][ $option ] ?? $default_value
		);
		Functions\when( 'update_option' )->alias(
			/** @param mixed $value Value to store. */
			function ( string $option, $value ): bool {
				$this->writes[] = $this->current . ':' . $option;

				if ( null !== $this->recorder ) {
					$this->recorder->record( 'write:' . $option . '@' . $this->current );
				}

				$this->options[ $this->current ][ $option ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $option ): bool {
				$exists = array_key_exists( $option, $this->options[ $this->current ] );
				unset( $this->options[ $this->current ][ $option ] );

				return $exists;
			}
		);
		Functions\when( 'get_network_option' )->alias(
			/**
			 * @param mixed $default_value Value if the option is missing.
			 * @return mixed
			 */
			fn( ?int $network, string $option, $default_value = false ) =>
				$this->network_options[ $network ?? $this->request_network ][ $option ] ?? $default_value
		);
		Functions\when( 'delete_network_option' )->alias(
			function ( ?int $network, string $option ): bool {
				$network = $network ?? $this->request_network;
				$exists  = array_key_exists( $option, $this->network_options[ $network ] );
				unset( $this->network_options[ $network ][ $option ] );

				return $exists;
			}
		);
		Functions\when( 'register_uninstall_hook' )->alias(
			/** @param mixed $callback Callback to store. */
			function ( string $file, $callback ): void {
				// Core's logic: write only when the entry is missing or differs.
				$callbacks = (array) ( $this->options[ $this->current ]['uninstall_plugins'] ?? [] );
				$basename  = \plugin_basename( $file );

				if ( ! isset( $callbacks[ $basename ] ) || $callbacks[ $basename ] !== $callback ) {
					$callbacks[ $basename ] = $callback;
					\update_option( 'uninstall_plugins', $callbacks );
				}
			}
		);
	}

	/**
	 * @param int $site Site to switch to.
	 *
	 * @throws LogicException On a single site, where WordPress has no such function.
	 */
	public function switch_to_blog( int $site ): bool {
		if ( ! $this->multisite ) {
			throw new LogicException( 'switch_to_blog() only exists on multisite.' );
		}

		$this->switches[] = $site;
		$this->stack[]    = $this->current;
		$this->current    = $site;

		return true;
	}

	public function restore_current_blog(): bool {
		if ( [] === $this->stack ) {
			return false;
		}

		$this->current = array_pop( $this->stack );

		return true;
	}

	/**
	 * @param array{fields?: string, orderby?: string, network_id?: int, offset?: int, number?: int} $args Query.
	 * @return list<int> Site IDs of the page.
	 *
	 * @throws LogicException If the query is not the one the lifecycle dispatch makes.
	 */
	public function get_sites( array $args ): array {
		if ( ( $args['fields'] ?? null ) !== 'ids' || ( $args['orderby'] ?? null ) !== 'id' ) {
			throw new LogicException( 'The lifecycle dispatch asks for site IDs ordered by ID.' );
		}

		$network = $args['network_id'] ?? 0;
		$offset  = $args['offset'] ?? 0;
		$number  = $args['number'] ?? 100;

		$this->queries[] = "sites:{$network}:{$offset}";

		$ids = array_keys( array_filter( $this->sites, static fn( int $of ): bool => $of === $network ) );

		return array_slice( $ids, $offset, $number );
	}

	/**
	 * @param array{offset?: int, number?: int} $args Query arguments.
	 * @return list<int> Network IDs of the page.
	 */
	public function get_networks( array $args ): array {
		$offset = $args['offset'] ?? 0;
		$number = $args['number'] ?? 100;

		$this->queries[] = "networks:{$offset}";

		return array_slice( array_keys( $this->networks ), $offset, $number );
	}

	/**
	 * Also record each option write in a recorder, to see when it happens.
	 */
	public function record_writes_into( LifecycleRecorder $recorder ): void {
		$this->recorder = $recorder;
	}

	/**
	 * Mark a site as one that core did not manage to set up.
	 */
	public function fail_initialization( int $site ): void {
		$this->uninitialized[ $site ] = true;
	}

	public function current_site(): int {
		return $this->current;
	}

	/**
	 * Number of switched sites not restored yet.
	 */
	public function stack_depth(): int {
		return count( $this->stack );
	}

	/**
	 * @return list<int> Sites switched to, in order.
	 */
	public function switches(): array {
		return $this->switches;
	}

	/**
	 * @return list<string> Pages asked for, in order.
	 */
	public function queries(): array {
		return $this->queries;
	}

	/**
	 * @return list<string> Option writes, as "site:option", in order.
	 */
	public function writes(): array {
		return $this->writes;
	}

	/**
	 * @param mixed $value Value to store.
	 */
	public function set_option( int $site, string $option, $value ): void {
		$this->options[ $site ][ $option ] = $value;
	}

	/**
	 * @return mixed Value of the option, or null.
	 */
	public function option( int $site, string $option ) {
		return $this->options[ $site ][ $option ] ?? null;
	}

	public function has_option( int $site, string $option ): bool {
		return array_key_exists( $option, $this->options[ $site ] );
	}

	/**
	 * @param mixed $value Value to store.
	 */
	public function set_network_option( int $network, string $option, $value ): void {
		$this->network_options[ $network ][ $option ] = $value;
	}

	public function has_network_option( int $network, string $option ): bool {
		return array_key_exists( $option, $this->network_options[ $network ] );
	}

	/**
	 * Activate the plugin for a whole network, as WordPress records it.
	 */
	public function activate_for_network( int $network ): void {
		$this->network_options[ $network ]['active_sitewide_plugins'] = [ self::BASENAME => time() ];
	}

	/**
	 * Activate the plugin on one site, as WordPress records it.
	 */
	public function activate_on_site( int $site ): void {
		$this->options[ $site ]['active_plugins'] = [ self::BASENAME ];
	}

	/**
	 * The uninstall callback stored on a site, if any.
	 *
	 * @return mixed Callback, or null.
	 */
	public function uninstall_callback( int $site ) {
		$callbacks = $this->options[ $site ]['uninstall_plugins'] ?? [];

		return is_array( $callbacks ) ? ( $callbacks[ self::BASENAME ] ?? null ) : null;
	}

	/**
	 * Remove the uninstall callback of a site, as core does before it includes
	 * the main plugin file to uninstall it.
	 */
	public function remove_uninstall_callback( int $site ): void {
		$callbacks = (array) ( $this->options[ $site ]['uninstall_plugins'] ?? [] );
		unset( $callbacks[ self::BASENAME ] );
		$this->options[ $site ]['uninstall_plugins'] = $callbacks;
	}
}
