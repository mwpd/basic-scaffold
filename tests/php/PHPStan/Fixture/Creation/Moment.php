<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation;

use DateTimeImmutable;

final class Moment extends DateTimeImmutable {

	public function copy(): DateTimeImmutable {
		return new parent();
	}
}
