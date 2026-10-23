<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\NetworkActivateable;
use MWPD\BasicScaffold\Infrastructure\NetworkDeactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;
use MWPD\BasicScaffold\Infrastructure\SiteDeactivateable;

/**
 * Implements the parameterless, site-scoped and network-scoped interfaces.
 */
final class FullLifecycleService implements
	Service,
	Activateable,
	Deactivateable,
	SiteActivateable,
	SiteDeactivateable,
	NetworkActivateable,
	NetworkDeactivateable {

	use RecordsScopedLifecycle;

	private const NAME = 'all';
}
