<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\Conditional;

final class ConditionalOnly implements Conditional {
	public static function is_needed(): bool { return true; }
}
