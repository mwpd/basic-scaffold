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

namespace MWPD\BasicScaffold\Exception;

use LogicException;

/**
 * Exception for an injection chain that is used the wrong way.
 *
 * This is a programming error in the injector rather than a failure while
 * running, so it extends LogicException.
 */
final class InvalidInjectionChain extends LogicException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for reading the last class of an
	 * injection chain that has no resolutions yet.
	 */
	public static function for_empty_chain(): self {
		return new self( 'Access to injection chain before any resolution was made.' );
	}
}
