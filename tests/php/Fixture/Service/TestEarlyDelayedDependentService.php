<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A delayed service that asks for an early priority on init, but depends on a
 * service that may only be ready later.
 *
 * It uses the default trait and overrides its method, to show that a consuming
 * class can.
 */
class TestEarlyDelayedDependentService implements Service, Delayed, HasDependencies {
	use DefaultRegistrationPriority;

	public static function get_registration_action(): string {
		return 'init';
	}

	public static function get_registration_priority(): int {
		return 5;
	}

	public static function get_dependencies(): array {
		return [ 'late_service' ];
	}
}
