<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A delayed service at the very last priority on wp, the same priority at
 * which the plugin resumes its pending services, depending on a service on
 * init.
 */
class TestLastDelayedService implements Service, Delayed, HasDependencies {
	public static function get_registration_action(): string {
		return 'wp';
	}

	public static function get_registration_priority(): int {
		return PHP_INT_MAX;
	}

	public static function get_dependencies(): array {
		return [ 'delayed_service' ];
	}
}
