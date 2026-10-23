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

namespace MWPD\BasicScaffold\Tests\Integration;

use Brain\Monkey;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use RuntimeException;
use Throwable;
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

	/**
	 * Assert that a failure from a sample's failing template fixture kept the
	 * template's own exception as its cause.
	 *
	 * A missing template has no cause to keep: its InvalidPath is the
	 * original failure.
	 *
	 * @param Throwable $exception Exception that surfaced from the sample.
	 */
	protected function assert_sample_template_cause( Throwable $exception ): void {
		if ( ! $exception instanceof FailedToLoadView ) {
			return;
		}

		$cause = $exception->getPrevious();
		$this->assertInstanceOf( RuntimeException::class, $cause );
		$this->assertSame( 'The sample template failed.', $cause->getMessage() );
	}
}
