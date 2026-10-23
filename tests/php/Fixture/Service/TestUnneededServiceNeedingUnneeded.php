<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * Not needed in the same requests as the service it depends on.
 */
class TestUnneededServiceNeedingUnneeded implements Service, Conditional, HasDependencies {
	public static function is_needed(): bool {
		return false;
	}

	public static function get_dependencies(): array {
		return [ 'unneeded_service' ];
	}
}
