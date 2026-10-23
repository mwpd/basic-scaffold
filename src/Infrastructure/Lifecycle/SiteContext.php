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

/**
 * The site a scoped lifecycle call happens in.
 *
 * The site is switched to when the service is called, so WordPress functions
 * work on it directly. The context names it, the network it belongs to and
 * the scope of the event the call is part of.
 */
final class SiteContext {

	private SiteId $site_id;

	private NetworkId $network_id;

	private LifecycleScope $scope;

	/**
	 * Instantiate a SiteContext object.
	 *
	 * @param SiteId         $site_id    Site the call happens in.
	 * @param NetworkId      $network_id Network the site belongs to.
	 * @param LifecycleScope $scope      Scope of the event the call is part of.
	 */
	private function __construct( SiteId $site_id, NetworkId $network_id, LifecycleScope $scope ) {
		$this->site_id    = $site_id;
		$this->network_id = $network_id;
		$this->scope      = $scope;
	}

	/**
	 * Create the context of a call in one site.
	 *
	 * @param SiteId         $site_id    Site the call happens in.
	 * @param NetworkId      $network_id Network the site belongs to.
	 * @param LifecycleScope $scope      Scope of the event the call is part of.
	 */
	public static function for_site( SiteId $site_id, NetworkId $network_id, LifecycleScope $scope ): self {
		return new self( $site_id, $network_id, $scope );
	}

	/**
	 * Get the site the call happens in.
	 */
	public function site_id(): SiteId {
		return $this->site_id;
	}

	/**
	 * Get the network the site belongs to.
	 *
	 * Switching sites does not switch networks, so network options are
	 * addressed with this ID.
	 */
	public function network_id(): NetworkId {
		return $this->network_id;
	}

	/**
	 * Get the scope of the event the call is part of: network-wide for a
	 * network activation or deactivation, a new site on a network where the
	 * plugin is network-active, and an uninstall on multisite.
	 */
	public function scope(): LifecycleScope {
		return $this->scope;
	}
}
