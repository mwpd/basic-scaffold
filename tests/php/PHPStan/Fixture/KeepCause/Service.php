<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\KeepCause;

use RuntimeException;
use Throwable;

final class Service {

	public function translate(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			throw Failure::from_failure();
		}
	}

	public function create(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			if ( $caught->getMessage() !== '' ) {
				throw new RuntimeException( 'replacement' );
			}
		}
	}

	public function keep(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			throw Failure::from_failure( $caught );
		}
	}

	public function keep_new(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			throw new RuntimeException( 'replacement', 0, $caught );
		}
	}

	public function rethrow(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			throw $caught;
		}
	}

	public function discard(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			// A comment must not hide a discarded cause.
		}
	}

	public function allowed(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			// This fixture models a reviewed recovery that needs no action.
		}
	}

	public function nested(): void {
		try {
			throw new RuntimeException( 'outer' );
		} catch ( Throwable $outer ) {
			try {
				throw Failure::from_failure( $outer );
			} catch ( Throwable $inner ) {
				throw Failure::from_failure( $outer );
			}
		}
	}

	public function deferred(): void {
		try {
			throw new RuntimeException( 'original' );
		} catch ( Throwable $caught ) {
			$callback = static function (): void {
				throw Failure::from_failure();
			};
			$callback();
		}
	}
}
