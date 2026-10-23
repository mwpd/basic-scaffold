<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\ConstructorsOnlyAssign;

use MWPD\BasicScaffold\Infrastructure\Service;

final class BranchService implements Service {

	public function __construct( string $value ) {
		if ( $value !== '' ) {
			$this->value = $value;
		}
	}
}
