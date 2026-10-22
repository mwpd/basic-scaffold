<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors;

use WP_Error;

function last_error(): ?WP_Error {
	return null;
}
