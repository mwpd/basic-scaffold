<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A delayed service that is needed once its action fires, and counts how often
 * it was asked and registered.
 */
final class CountingDelayedNeededService implements Service, Registerable, Conditional, Delayed {
	use DefaultRegistrationPriority;

	public static int $asked = 0;

	public static int $registered = 0;

	public static function get_registration_action(): string {
		return 'init';
	}

	public static function is_needed(): bool {
		++self::$asked;

		return true;
	}

	public function register(): void {
		++self::$registered;
	}
}
