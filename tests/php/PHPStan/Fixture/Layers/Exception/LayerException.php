<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Exception;

use RuntimeException;

final class LayerException extends RuntimeException {

	public static function for_reason(): self {
		return new self( 'Reason.' );
	}
}
