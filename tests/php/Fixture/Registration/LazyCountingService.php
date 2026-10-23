<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that is not Registerable, so the plugin stores it lazily, and
 * counts how often it was built.
 */
final class LazyCountingService implements Service {

	public static int $built = 0;

	public function __construct() {
		++self::$built;
	}
}
