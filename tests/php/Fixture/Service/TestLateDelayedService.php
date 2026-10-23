<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A delayed service that implements its own, later registration priority.
 */
class TestLateDelayedService implements Service, Delayed {
	public static function get_registration_action(): string {
		return 'init';
	}

	public static function get_registration_priority(): int {
		return 20;
	}
}
