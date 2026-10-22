<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors;

use WP_Error;

final class Adapter {

	public function to_wordpress(): WP_Error {
		return new WP_Error();
	}
}
