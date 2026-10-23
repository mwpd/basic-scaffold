<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestUnneededService implements Service, Conditional {
	public static function is_needed(): bool {
		return false;
	}
}
