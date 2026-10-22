<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation;

final class Consumer {

	private Contract $contract;

	public function __construct( ?Contract $contract = null ) {
		$this->contract = $contract ?? new DefaultContract();
	}

	/**
	 * @param class-string $class_name Class to create.
	 */
	public function create( string $class_name ): void {
		$service   = new Service();
		$library   = new \ArrayObject();
		$value     = new ValueObject();
		$exception = new CreationException( 'Failed.' );
		$anonymous = new class() {
		};
		$dynamic   = new $class_name();
		$closure   = static fn(): Service => new Service();
	}
}
