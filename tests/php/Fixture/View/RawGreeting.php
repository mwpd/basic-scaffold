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

use MWPD\BasicScaffold\Infrastructure\Presenter;

/**
 * A presenter interface that declares a method of the template scope.
 */
interface RawGreeting extends Presenter {

	/**
	 * @param string $property Property.
	 */
	public function raw( $property ): string;
}
