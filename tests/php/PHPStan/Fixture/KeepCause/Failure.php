<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\KeepCause;

use RuntimeException;
use Throwable;

final class Failure extends RuntimeException {

	public static function from_failure( ?Throwable $previous = null ): self {
		return new self( 'Translated failure.', 0, $previous );
	}
}
