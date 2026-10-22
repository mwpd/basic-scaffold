<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure;

interface Registerable {

	public function register(): void;
}
