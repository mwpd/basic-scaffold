<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\Delayed;

final class DelayedOnly implements Delayed {
	public static function get_registration_action(): string { return "wp"; }
}
