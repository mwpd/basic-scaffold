<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\NetworkActivateable;
use MWPD\BasicScaffold\Infrastructure\NetworkDeactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * Set up and cleaned up once for a network.
 */
final class NetworkLifecycleService implements Service, NetworkActivateable, NetworkDeactivateable {
	use RecordsScopedLifecycle;

	private const NAME = 'network';
}
