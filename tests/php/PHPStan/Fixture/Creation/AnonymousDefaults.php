<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation;

use RuntimeException;

final class AnonymousDefaults {

	private Contract $contract;

	/**
	 * @var callable
	 */
	private $callback;

	public function __construct( ?Contract $contract = null ) {
		$this->contract = $contract ?? new class() implements Contract {

			public function work(): void {
			}
		};
		$this->callback = function ( ?DefaultContract $contract = null ): void {
			$this->contract = $contract ?? new DefaultContract();
		};
	}

	public function fail(): void {
		throw new class( 'Failed.' ) extends RuntimeException {
		};
	}
}
