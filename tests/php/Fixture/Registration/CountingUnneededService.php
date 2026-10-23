<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that is not needed, and counts how often it was asked.
 */
final class CountingUnneededService implements Service, Conditional {

	public static int $asked = 0;

	public static function is_needed(): bool {
		++self::$asked;

		return false;
	}
}
