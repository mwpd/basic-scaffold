<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A delayed service with a late priority on wp, depending on a service that is
 * delayed to an earlier action.
 */
class TestLateDelayedDependentService implements Service, Delayed, HasDependencies {
	public static function get_registration_action(): string {
		return 'wp';
	}

	public static function get_registration_priority(): int {
		return 20;
	}

	public static function get_dependencies(): array {
		return [ 'delayed_service' ];
	}
}
