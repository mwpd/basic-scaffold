<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestCircularA implements Service, HasDependencies {
	public static function get_dependencies(): array {
		return [ 'circular_b' ];
	}
}
