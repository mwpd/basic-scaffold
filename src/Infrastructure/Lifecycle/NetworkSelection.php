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
 * The networks a site runner visits.
 *
 * On a single site, every selection means the one implicit network.
 */
final class NetworkSelection {

	private bool $all;

	/**
	 * Instantiate a NetworkSelection object.
	 *
	 * @param bool $all Whether every network is selected.
	 */
	private function __construct( bool $all ) {
		$this->all = $all;
	}

	/**
	 * Select the current network.
	 */
	public static function current(): self {
		return new self( false );
	}

	/**
	 * Select every network of the installation.
	 */
	public static function all(): self {
		return new self( true );
	}

	/**
	 * Check whether every network of the installation is selected.
	 */
	public function is_all(): bool {
		return $this->all;
	}
}
