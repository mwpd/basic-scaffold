<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;
use MWPD\BasicScaffold\Infrastructure\SiteDeactivateable;

/**
 * Set up and cleaned up in each site.
 */
final class SiteLifecycleService implements Service, SiteActivateable, SiteDeactivateable {
	use RecordsScopedLifecycle;

	private const NAME = 'site';
}
