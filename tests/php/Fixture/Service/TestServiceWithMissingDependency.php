<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestServiceWithMissingDependency implements Service, HasDependencies {
	public static function get_dependencies(): array {
		return [ 'non_existent_service' ];
	}
}
