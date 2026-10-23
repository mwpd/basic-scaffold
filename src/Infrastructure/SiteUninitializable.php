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
 * Something that is cleaned up in a site before it is deleted.
 *
 * The plugin calls it from WordPress's wp_uninitialize_site action, once
 * core has validated the deletion and before it drops the site's tables, with
 * the site switched to. It runs whether or not the plugin is still active on
 * that site, so data left from an earlier activation is cleaned up too. It
 * only runs in a request where the plugin is loaded: a plugin that is only
 * active on the deleted site is not loaded when that site is deleted from the
 * network admin.
 *
 * An exception thrown here propagates and can interrupt the deletion of the
 * site; the site context is restored first.
 */
interface SiteUninitializable {

	/**
	 * Clean the service up in a site that is about to be deleted.
	 *
	 * Called with the site switched to, while its tables still exist. Must
	 * tolerate data that is already gone.
	 *
	 * @param SiteContext $context Where the call happens.
	 */
	public function uninitialize_site( SiteContext $context ): void;
}
