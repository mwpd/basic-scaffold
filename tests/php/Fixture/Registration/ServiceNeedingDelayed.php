<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that is not delayed itself, but depends on the delayed service.
 */
final class ServiceNeedingDelayed implements Service, HasDependencies {

	public static function get_dependencies(): array {
		return [ 'delayed_service' ];
	}
}
