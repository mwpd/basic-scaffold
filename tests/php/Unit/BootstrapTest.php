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

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Functions;

final class BootstrapTest extends TestCase {

	/**
	 * WordPress includes the plugin's main file in the global scope, so every
	 * variable it defines becomes a global that can clash with WordPress core
	 * or with other plugins.
	 *
	 * Including the file inside this method puts its variables into the
	 * method's local scope instead, where they can be compared.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_it_defines_no_variables(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/' );
		}

		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );

		$before = array_keys( get_defined_vars() );

		include dirname( __DIR__, 3 ) . '/basic-scaffold.php';

		$this->assertSame( [], array_values( array_diff( array_keys( get_defined_vars() ), $before, [ 'before' ] ) ) );
	}
}
