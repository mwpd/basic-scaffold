<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteInitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninitializable;

/**
 * Set up in a new site and cleaned up in a site that is deleted.
 */
final class SiteEventsService implements Service, SiteInitializable, SiteUninitializable {
	use RecordsScopedLifecycle;

	private const NAME = 'site-events';
}
