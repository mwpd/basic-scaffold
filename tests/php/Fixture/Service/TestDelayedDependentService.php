<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * Registers at "wp", and depends on a service that registers at "init".
 */
class TestDelayedDependentService implements Service, Delayed, HasDependencies {
	use DefaultRegistrationPriority;

	public static function get_registration_action(): string {
		return 'wp';
	}

	public static function get_dependencies(): array {
		return [ 'delayed_service' ];
	}
}
