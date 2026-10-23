<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Conditional;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure\Delayed;

final class DelayedQueryConditional implements Conditional, Delayed {

	public static function is_needed(): bool {
		return is_singular();
	}

	public static function get_registration_action(): string {
		return 'wp';
	}
}
