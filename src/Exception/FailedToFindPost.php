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
	 * Create a new instance of the exception for a main query that did not
	 * ask for a post.
	 */
	public static function for_main_query(): self {
		return new self(
			'The main query did not ask for a post. A service that needs the queried post has to wait for the main'
			. ' query, for example by implementing Delayed with the "wp" action, be needed only on singular views,'
			. ' and ask for the post only while the main loop renders it.'
		);
	}
}
