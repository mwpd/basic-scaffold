<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestMultiDependentService implements Service, HasDependencies {
	public static function get_dependencies(): array {
		return [ 'delayed_service_1', 'delayed_service_2' ];
	}
}
