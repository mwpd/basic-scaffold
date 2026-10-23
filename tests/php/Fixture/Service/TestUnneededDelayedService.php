<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestUnneededDelayedService implements Service, Conditional, Delayed {
	public static function get_registration_action(): string {
		return 'init';
	}

	public static function is_needed(): bool {
		return false;
	}
}
