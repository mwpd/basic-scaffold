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

namespace MWPD\BasicScaffold\Tests\Fixture\View;

/**
 * A presenter whose method the template scope shadows.
 */
final class FixedRawGreeting implements RawGreeting {

	/**
	 * @param string $property Property.
	 */
	public function raw( $property ): string {
		return 'presenter';
	}
}
