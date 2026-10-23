<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\Registerable;

final class RegisterableOnly implements Registerable {
	public function register(): void {}
}
