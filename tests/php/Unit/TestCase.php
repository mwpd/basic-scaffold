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

use Brain\Monkey;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PHPUnitTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

abstract class TestCase extends PHPUnitTestCase {

	// Adds Mockery expectations to the PHPUnit assertions count.
	use MockeryPHPUnitIntegration;

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
	}

	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Create a debug mode that is on or off.
	 *
	 * @param bool $is_debug_mode Whether debug mode is on.
	 * @return DebugMode Debug mode double.
	 */
	protected function debug_mode( bool $is_debug_mode ): DebugMode {
		$debug_mode = $this->createMock( DebugMode::class );
		$debug_mode->method( 'is_debug_mode' )->willReturn( $is_debug_mode );

		return $debug_mode;
	}
}
