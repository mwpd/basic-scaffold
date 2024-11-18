<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestDelayedService implements Service, Delayed {
	public static function get_registration_action(): string {
		return 'init';
	}
}
