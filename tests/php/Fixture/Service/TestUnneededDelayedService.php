<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestUnneededDelayedService implements Service, Conditional, Delayed {
	use DefaultRegistrationPriority;

	public static function get_registration_action(): string {
		return 'init';
	}

	public static function is_needed(): bool {
		return false;
	}
}
