<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\NetworkActivateable;
use MWPD\BasicScaffold\Infrastructure\NetworkDeactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * Implements the parameterless and the network-scoped interfaces.
 */
final class LegacyAndNetworkLifecycleService implements
	Service,
	Activateable,
	Deactivateable,
	NetworkActivateable,
	NetworkDeactivateable {

	use RecordsScopedLifecycle;

	private const NAME = 'legacy+network';
}
