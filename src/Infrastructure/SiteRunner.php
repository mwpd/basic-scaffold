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

use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkSelection;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteSelection;

/**
 * Runs lifecycle work in each of a set of sites or networks.
 *
 * The plugin hands every multisite traversal to a site runner, so how sites
 * are visited is replaceable without touching the plugin. A runner works
 * synchronously: when a run method returns, the work has finished in every
 * selected site. A custom runner can page differently or report progress,
 * for example from WP-CLI, but it does not defer work to later requests.
 *
 * On the first exception a task throws, the runner restores the site that
 * was current before the run and lets that exception propagate unchanged.
 * It visits no further site, and nothing that already ran is undone.
 */
interface SiteRunner {

	/**
	 * Run a task once in each selected site, with that site switched to.
	 *
	 * @param SiteSelection                    $sites Sites to visit.
	 * @param callable(SiteId, NetworkId):void $task  Work to do in each site,
	 *                                                receiving the site and
	 *                                                its network.
	 */
	public function run_on_sites( SiteSelection $sites, callable $task ): void;

	/**
	 * Run a task once for each selected network, with its main site switched
	 * to.
	 *
	 * @param NetworkSelection         $networks Networks to visit.
	 * @param callable(NetworkId):void $task     Work to do for each network.
	 */
	public function run_on_networks( NetworkSelection $networks, callable $task ): void;
}
