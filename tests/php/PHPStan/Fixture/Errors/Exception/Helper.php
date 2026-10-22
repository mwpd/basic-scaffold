<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Exception;

trait Helper {

	public static function for_failure(): self {
		return new self( 'Failure.' );
	}
}
