<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\{DefaultRegistrationPriority, Delayed};

final class FullService extends BaseService implements Conditional, Delayed {
	use DefaultRegistrationPriority;

	public function register(): void {}
	public static function is_needed(): bool { return true; }
	public static function get_registration_action(): string { return 'wp'; }
}
