<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors;

use LogicException;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Exception\GoodException;
use RuntimeException;
use Throwable;
use WP_Error;

final class Service {

	private ?WP_Error $last_error = null;

	/**
	 * @var array<string>|WP_Error
	 */
	private $result = [];

	public function report(): void {
		error_log( 'Something failed.' );
		\trigger_error( 'Something failed.' );
		_doing_it_wrong( __METHOD__, 'Wrong.', '1.0.0' );
	}

	public function fail(): WP_Error {
		return new WP_Error();
	}

	public function translate(): void {
		$result = \wp_fixture_query();

		if ( $result instanceof WP_Error || \is_wp_error( $result ) ) {
			throw GoodException::from_value( 'result' );
		}
	}

	public function throw_generic(): void {
		throw new RuntimeException( 'Generic.' );
	}

	public function rethrow(): void {
		try {
			$this->throw_generic();
		} catch ( Throwable $throwable ) {
			throw $throwable;
		}
	}

	public function throw_something_else(): void {
		$other = new LogicException( 'Other.' );

		try {
			$this->throw_generic();
		} catch ( RuntimeException $exception ) {
			throw $other;
		}
	}

	public function create_directly(): void {
		throw new GoodException( 'Direct.' );
	}
}
