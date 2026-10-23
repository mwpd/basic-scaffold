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
 * The sites a site runner visits.
 *
 * On a single site, every selection means the current site.
 */
final class SiteSelection {

	private ?SiteId $site_id;

	private ?NetworkId $network_id;

	/**
	 * Instantiate a SiteSelection object.
	 *
	 * @param SiteId|null    $site_id    The one site selected, if any.
	 * @param NetworkId|null $network_id Network of the selection, or null for the current site.
	 */
	private function __construct( ?SiteId $site_id, ?NetworkId $network_id ) {
		$this->site_id    = $site_id;
		$this->network_id = $network_id;
	}

	/**
	 * Select the current site, without switching.
	 */
	public static function current(): self {
		return new self( null, null );
	}

	/**
	 * Select one site.
	 *
	 * @param SiteId    $site_id    Site to visit.
	 * @param NetworkId $network_id Network the site belongs to.
	 */
	public static function site( SiteId $site_id, NetworkId $network_id ): self {
		return new self( $site_id, $network_id );
	}

	/**
	 * Select every site of a network.
	 *
	 * @param NetworkId $network_id Network whose sites to visit.
	 */
	public static function network( NetworkId $network_id ): self {
		return new self( null, $network_id );
	}

	/**
	 * Check whether the selection is the current site.
	 */
	public function is_current_site(): bool {
		return null === $this->network_id;
	}

	/**
	 * Get the one site selected, if the selection is a single site.
	 *
	 * @return SiteId|null Site, or null for the current site or a network.
	 */
	public function site_id(): ?SiteId {
		return $this->site_id;
	}

	/**
	 * Get the network of the selection.
	 *
	 * @return NetworkId|null Network of the selected site or sites, or null for
	 *                        the current site.
	 */
	public function network_id(): ?NetworkId {
		return $this->network_id;
	}
}
