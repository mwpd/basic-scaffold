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

namespace MWPD\BasicScaffold\Infrastructure\Lifecycle;

use MWPD\BasicScaffold\Exception\FailedToRestoreSite;
use MWPD\BasicScaffold\Exception\InvalidArgument;
use MWPD\BasicScaffold\Infrastructure\SiteRunner;

/**
 * Visits the selected sites one after the other, within the current request.
 *
 * Sites and networks are looked up in pages, so memory stays bounded however
 * large the installation is. Pagination does not bound the time the request
 * takes, though: on a very large network, the work can run into the PHP time
 * limit, and a custom runner, for example one run from WP-CLI, is the answer.
 *
 * Pages are read by offset, ordered by ID. A site created or deleted while a
 * run pages through its network can shift the pages, so a run that has to
 * see such a site is started again once the change is done; every lifecycle
 * step is idempotent, so running it again is safe.
 *
 * On a single site, every selection is the current site and nothing is
 * switched: switch_to_blog() only exists on multisite.
 */
final class SynchronousSiteRunner implements SiteRunner {

	/**
	 * Number of sites or networks looked up at a time, unless another is given.
	 *
	 * @var positive-int
	 */
	private const DEFAULT_PAGE_SIZE = 100;

	/**
	 * Number of sites or networks looked up at a time.
	 *
	 * @var positive-int
	 */
	private int $page_size;

	/**
	 * Instantiate a SynchronousSiteRunner object.
	 *
	 * @param int $page_size Optional. Number of sites or networks to look up at
	 *                       a time. Defaults to 100.
	 *
	 * @throws InvalidArgument If the page size is not positive.
	 */
	public function __construct( int $page_size = self::DEFAULT_PAGE_SIZE ) {
		if ( $page_size < 1 ) {
			throw InvalidArgument::from_page_size( $page_size );
		}

		$this->page_size = $page_size;
	}

	/**
	 * Run a task once in each selected site, with that site switched to.
	 *
	 * @param SiteSelection                    $sites Sites to visit.
	 * @param callable(SiteId, NetworkId):void $task  Work to do in each site.
	 *
	 * @throws InvalidArgument If WordPress hands over an invalid ID.
	 * @throws FailedToRestoreSite If a task leaves another site switched to.
	 */
	public function run_on_sites( SiteSelection $sites, callable $task ): void {
		$site_id    = $sites->site_id();
		$network_id = $sites->network_id();

		if ( ! \is_multisite() || null === $network_id ) {
			$task(
				SiteId::from_wordpress( \get_current_blog_id() ),
				NetworkId::from_wordpress( \get_current_network_id() )
			);

			return;
		}

		if ( null !== $site_id ) {
			$this->run_in_site(
				$site_id,
				static function () use ( $task, $site_id, $network_id ): void {
					$task( $site_id, $network_id );
				}
			);

			return;
		}

		$offset = 0;

		do {
			$page = \get_sites(
				[
					'fields'                 => 'ids',
					'network_id'             => $network_id->to_int(),
					'number'                 => $this->page_size,
					'offset'                 => $offset,
					'orderby'                => 'id',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_site_meta_cache' => false,
				]
			);

			foreach ( $page as $id ) {
				$site = SiteId::from_wordpress( $id );

				$this->run_in_site(
					$site,
					static function () use ( $task, $site, $network_id ): void {
						$task( $site, $network_id );
					}
				);
			}

			$offset += $this->page_size;
			$visited = count( $page );
		} while ( $visited === $this->page_size );
	}

	/**
	 * Run a task once for each selected network, with its main site switched
	 * to.
	 *
	 * @param NetworkSelection         $networks Networks to visit.
	 * @param callable(NetworkId):void $task     Work to do for each network.
	 *
	 * @throws InvalidArgument If WordPress hands over an invalid ID.
	 * @throws FailedToRestoreSite If a task leaves another site switched to.
	 */
	public function run_on_networks( NetworkSelection $networks, callable $task ): void {
		if ( ! \is_multisite() ) {
			$task( NetworkId::from_wordpress( \get_current_network_id() ) );

			return;
		}

		if ( ! $networks->is_all() ) {
			$this->run_for_network( NetworkId::from_wordpress( \get_current_network_id() ), $task );

			return;
		}

		$offset = 0;

		do {
			$page = \get_networks(
				[
					'fields'        => 'ids',
					'number'        => $this->page_size,
					'offset'        => $offset,
					'orderby'       => 'id',
					'order'         => 'ASC',
					'no_found_rows' => true,
				]
			);

			// get_networks() is typed loosely; with 'fields' => 'ids' it returns
			// a list of IDs, and anything else is rejected as an invalid ID.
			$page = is_array( $page ) ? $page : [ $page ];

			foreach ( $page as $id ) {
				$this->run_for_network( NetworkId::from_wordpress( $id ), $task );
			}

			$offset += $this->page_size;
			$visited = count( $page );
		} while ( $visited === $this->page_size );
	}

	/**
	 * Run a task for one network in its main site.
	 *
	 * @param NetworkId                $network_id Network to run the task for.
	 * @param callable(NetworkId):void $task       Work to do for the network.
	 *
	 * @throws InvalidArgument If WordPress hands over an invalid ID.
	 * @throws FailedToRestoreSite If the task leaves another site switched to.
	 */
	private function run_for_network( NetworkId $network_id, callable $task ): void {
		$this->run_in_site(
			SiteId::from_wordpress( \get_main_site_id( $network_id->to_int() ) ),
			static function () use ( $task, $network_id ): void {
				$task( $network_id );
			}
		);
	}

	/**
	 * Run work with a site switched to, and switch back afterwards, whether
	 * the work returns or throws.
	 *
	 * WordPress keeps a stack of switched sites, and restore_current_blog()
	 * only takes one entry off it. Work that switches sites itself must
	 * restore them before it returns. If it does not, the site that is current
	 * afterwards is not the one switched to here: the switches it left are
	 * restored one by one until it is, then this one, and the work fails,
	 * unless it already failed with an exception of its own, which is never
	 * replaced. Leftover switches that happen to end on the same site cannot
	 * be told apart without reading WordPress's globals.
	 *
	 * The switch itself happens inside the protected part: switch_to_blog()
	 * runs the switch_blog action once it has switched, and a callback that
	 * throws there must not leave the site switched either.
	 *
	 * @param SiteId   $site_id Site to switch to.
	 * @param callable $work    Work to do in the site.
	 *
	 * @throws FailedToRestoreSite If the work leaves another site switched to.
	 */
	private function run_in_site( SiteId $site_id, callable $work ): void {
		$original = \get_current_blog_id();
		$target   = $site_id->to_int();
		$current  = $target;

		try {
			\switch_to_blog( $target );
			$work();
		} finally {
			$current = \get_current_blog_id();

			// Undo switches the work left behind, then the one made here,
			// unless the work already undid it.
			while ( ! in_array( \get_current_blog_id(), [ $target, $original ], true ) && \restore_current_blog() ) {
				continue;
			}

			if ( \get_current_blog_id() === $target ) {
				\restore_current_blog();
			}

			if ( \get_current_blog_id() !== $original ) {
				\switch_to_blog( $original );
			}
		}

		if ( $current !== $target ) {
			throw FailedToRestoreSite::for_unbalanced_switch( $target, $current );
		}
	}
}
