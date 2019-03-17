<?php declare( strict_types=1 );

/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

namespace MWPD\BasicScaffold\Infrastructure\Injector;

use LogicException;

/**
 * The injection chain is similar to a trace, keeping track of what we have done
 * so far and at what depth within the auto-wiring we currently are.
 *
 * It is used to detect circular dependencies, and can also be dumped for
 * debugging information.
 */
final class InjectionChain {

	/** @var array<object> */
	private $chain = [];

	/** @var array<bool> */
	private $resolutions = [];

	/**
	 * Add class to injection chain.
	 *
	 * @param string $class Class to add to injection chain.
	 * @return self Modified injection chain.
	 */
	public function add_to_chain( string $class ): self {
		$this->chain[] = $class;

		return $this;
	}

	/**
	 * Add resolution for circular reference detection.
	 *
	 * @param string $resolution Resolution to add.
	 * @return self Modified injection chain.
	 */
	public function add_resolution( string $resolution ): self {
		$this->resolutions[ $resolution ] = true;

		return $this;
	}

	/**
	 * Get the last class that was pushed to the injection chain.
	 *
	 * @return string Last class pushed to the injection chain.
	 */
	public function get_class(): string {
		if ( empty( $this->chain ) ) {
			throw new LogicException(
				'Access to injection chain before any resolution was made.'
			);
		}

		return \end( $this->chain );
	}

	/**
	 * Get the injection chain.
	 *
	 * @return array Chain of injections.
	 */
	public function get_chain(): array {
		return \array_reverse( $this->chain );
	}

	/**
	 * Check whether the injection chain already has a given resolution.
	 *
	 * @param string $resolution Resolution to check for
	 * @return bool Whether the resolution was found.
	 */
	public function has_resolution( string $resolution ): bool {
		return \array_key_exists( $resolution, $this->resolutions );
	}
}
