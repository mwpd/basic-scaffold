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
use Throwable;

/**
 * Exception thrown when a view file cannot be loaded.
 */
final class FailedToLoadView extends RuntimeException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception if the view file itself created
	 * an exception.
	 *
	 * @param string    $uri       URI of the file that is not accessible or
	 *                             not readable.
	 * @param Throwable $exception Exception that was thrown by the view file.
	 */
	public static function from_view_exception( $uri, $exception ): self {
		$message = \sprintf(
			'Could not load the View URI "%1$s". Reason: "%2$s".',
			$uri,
			$exception->getMessage()
		);

		return new self( $message, (int) $exception->getCode(), $exception );
	}
}
