<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\{DefaultRegistrationPriority, Delayed};
use MWPD\BasicScaffold\Infrastructure\Service;

final class DelayedService implements Service, Delayed {
	use DefaultRegistrationPriority;

	public static function get_registration_action(): string { return "wp"; }
}
