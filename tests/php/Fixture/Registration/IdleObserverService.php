<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\Registration\ObservesRegistration;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that could observe the registration, but does not in this
 * request, as the Query Monitor panel without Query Monitor.
 */
final class IdleObserverService implements Service, ObservesRegistration {

	public static function observes_registration(): bool {
		return false;
	}
}
