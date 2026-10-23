<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure;

interface Conditional {

	public static function is_needed(): bool;
}
