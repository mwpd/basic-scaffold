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
 * Exception thrown when lifecycle work in a switched site leaves another site
 * switched to.
 */
final class FailedToRestoreSite extends RuntimeException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for work that switched to a site
	 * without restoring the one it ran in.
	 *
	 * @param int $target  Site the work ran in.
	 * @param int $current Site that was current when the work returned.
	 */
	public static function for_unbalanced_switch( int $target, int $current ): self {
		$message = \sprintf(
			'Lifecycle work in site %1$d returned with site %2$d switched to. Every switch_to_blog() in a'
			. ' lifecycle method needs its own restore_current_blog(). The original site was switched to again,'
			. ' and the remaining sites were not visited.',
			$target,
			$current
		);

		return new self( $message );
	}
}
