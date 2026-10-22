<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Elsewhere;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Exception\PluginException;
use RuntimeException;

final class MisplacedException extends RuntimeException implements PluginException {

	public static function for_reason(): self {
		return new self( 'Reason.' );
	}
}
