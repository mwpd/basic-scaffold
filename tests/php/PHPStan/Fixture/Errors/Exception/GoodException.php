<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Exception;

use InvalidArgumentException;

final class GoodException extends InvalidArgumentException implements PluginException {

	public static function from_value( string $value ): self {
		return new self( "Invalid value {$value}." );
	}

	public static function from_other( string $value ): self {
		return new self( "Other value {$value}." );
	}

	public static function code(): int {
		return 42;
	}
}
