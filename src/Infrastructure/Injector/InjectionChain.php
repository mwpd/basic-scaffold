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

namespace MWPD\BasicScaffold\Infrastructure\Injector;

use MWPD\BasicScaffold\Exception\InvalidInjectionChain;

/**
 * The injection chain is similar to a trace, keeping track of what we have done
 * so far and at what depth within the auto-wiring we currently are.
 *
 * It is used to detect circular dependencies, and can also be dumped for
 * debugging information.
 *
 * The chain is immutable: adding to it returns a modified copy. Each branch of
 * the object tree carries only its own ancestry, so a class that two branches
 * both depend on is not mistaken for a circular reference.
 */
final class InjectionChain {

	/**
	 * Chain of injections.
	 *
	 * @var array<string>
	 */
	private array $chain = [];

	/**
	 * Resolutions.
	 *
	 * @var array<bool>
	 */
	private array $resolutions = [];

	/**
	 * Add class to injection chain.
	 *
	 * @param string $class_name Class to add to injection chain.
	 * @return self Modified copy of the injection chain.
	 */
	public function add_to_chain( string $class_name ): self {
		$new_chain          = clone $this;
		$new_chain->chain[] = $class_name;

		return $new_chain;
	}

	/**
	 * Add resolution for circular reference detection.
	 *
	 * @param string $resolution Resolution to add.
	 * @return self Modified copy of the injection chain.
	 */
	public function add_resolution( string $resolution ): self {
		$new_chain                             = clone $this;
		$new_chain->resolutions[ $resolution ] = true;

		return $new_chain;
	}

	/**
	 * Get the last class that was pushed to the injection chain.
	 *
	 * @return string Last class pushed to the injection chain.
	 * @throws InvalidInjectionChain If the chain is empty.
	 * @phpstan-return class-string
	 */
	public function get_class(): string {
		if ( $this->chain === [] ) {
			throw InvalidInjectionChain::for_empty_chain();
		}

		/**
		 * This returns a class string.
		 *
		 * @phpstan-var class-string
		 */
		return \end( $this->chain ) ?: '';
	}

	/**
	 * Get the injection chain.
	 *
	 * @return array<string> Chain of injections.
	 */
	public function get_chain(): array {
		return \array_reverse( $this->chain );
	}

	/**
	 * Get the resolutions in the order they were made.
	 *
	 * @return array<string> Resolutions, from the first to the most recent.
	 */
	public function get_resolutions(): array {
		return \array_keys( $this->resolutions );
	}

	/**
	 * Check whether the injection chain already has a given resolution.
	 *
	 * @param string $resolution Resolution to check for.
	 * @return bool Whether the resolution was found.
	 */
	public function has_resolution( string $resolution ): bool {
		return \array_key_exists( $resolution, $this->resolutions );
	}
}
