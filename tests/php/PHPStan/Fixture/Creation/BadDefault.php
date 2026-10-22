<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation;

final class BadDefault {

	private Contract $contract;

	private Service $service;

	public function __construct( Contract $contract, ?Service $service = null ) {
		$this->contract = $contract ?? new DefaultContract();
		$this->service  = $service ?? new Service();
	}
}
