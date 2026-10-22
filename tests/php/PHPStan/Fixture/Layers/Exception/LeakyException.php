<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Exception;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Contract;
use RuntimeException;

final class LeakyException extends RuntimeException {

	public static function for_contract( Contract $contract ): self {
		return new self( get_class( $contract ) );
	}
}
