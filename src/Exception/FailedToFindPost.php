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

use RuntimeException;

/**
 * Exception for a post that WordPress cannot provide.
 */
final class FailedToFindPost extends RuntimeException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for a request that has no
	 * current post.
	 */
	public static function for_current_request(): self {
		return new self(
			'There is no current post. A service that needs the current post has to wait for the main query, for'
			. ' example by implementing Delayed with the "wp" action, and only be needed on singular views.'
		);
	}
}
