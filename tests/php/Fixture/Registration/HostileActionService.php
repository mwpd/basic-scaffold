<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A delayed service that waits for an action whose name is markup, which the
 * panel must print as text.
 */
final class HostileActionService implements Service, Registerable, Delayed {
	use DefaultRegistrationPriority;

	public const ACTION = '<img src=x onerror="alert(1)">';

	public static function get_registration_action(): string {
		return self::ACTION;
	}

	public function register(): void {}
}
