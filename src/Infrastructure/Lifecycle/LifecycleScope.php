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
 * Whether a lifecycle event concerns the current site or a whole network.
 *
 * WordPress passes a loosely typed flag to the activation and deactivation
 * hooks. The bridges in the main plugin file turn it into this value at the
 * boundary (principle 9), so the plugin never handles the raw flag.
 */
final class LifecycleScope {

	/**
	 * Whether the event concerns a whole network.
	 */
	private bool $network_wide;

	/**
	 * Instantiate a LifecycleScope object.
	 *
	 * @param bool $network_wide Whether the event concerns a whole network.
	 */
	private function __construct( bool $network_wide ) {
		$this->network_wide = $network_wide;
	}

	/**
	 * The event concerns the current site only.
	 */
	public static function current_site(): self {
		return new self( false );
	}

	/**
	 * The event concerns every site of the current network.
	 */
	public static function network_wide(): self {
		return new self( true );
	}

	/**
	 * Normalize the flag WordPress passes to the activation and deactivation
	 * hooks.
	 *
	 * Only a flag of exactly true on a multisite installation means a network
	 * event. Anything else, including a missing flag, means the current site.
	 *
	 * @param mixed $flag Flag as WordPress passed it.
	 */
	public static function from_wordpress_flag( $flag ): self {
		return new self( true === $flag && \is_multisite() );
	}

	/**
	 * Check whether the event concerns a whole network.
	 */
	public function is_network_wide(): bool {
		return $this->network_wide;
	}
}
