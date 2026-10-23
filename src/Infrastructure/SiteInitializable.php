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

use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;

/**
 * Something that is set up in a site created on a network where the plugin is network-active.
 *
 * The plugin calls it from WordPress's wp_initialize_site action, after core
 * has set the new site up, with the new site switched to. It is only called
 * when the plugin is network-active on the new site's network, and only in a
 * request where the plugin is loaded: a site created from another network,
 * for example, is not reached.
 *
 * An exception thrown here propagates and can interrupt the creation of the
 * site; the site context is restored first.
 */
interface SiteInitializable {

	/**
	 * Set the service up in a site that was just created.
	 *
	 * Called with the new site switched to. Must be safe to run again.
	 *
	 * @param SiteContext $context Where the call happens.
	 */
	public function initialize_site( SiteContext $context ): void;
}
