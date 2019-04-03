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

namespace MWPD\BasicScaffold\Exception;

use RuntimeException;

class FailedToLoadView
	extends RuntimeException
	implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception if the view file itself created
	 * an exception.
	 *
	 * @param string     $uri       URI of the file that is not accessible or
	 *                              not readable.
	 * @param \Exception $exception Exception that was thrown by the view file.
	 *
	 * @return static
	 */
	public static function from_view_exception( $uri, $exception ) {
		$message = \sprintf(
			'Could not load the View URI "%1$s". Reason: "%2$s".',
			$uri,
			$exception->getMessage()
		);

		return new static( $message, (int) $exception->getCode(), $exception );
	}
}
