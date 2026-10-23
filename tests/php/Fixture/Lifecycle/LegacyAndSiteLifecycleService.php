<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;
use MWPD\BasicScaffold\Infrastructure\SiteDeactivateable;

/**
 * Implements the parameterless and the site-scoped interfaces.
 */
final class LegacyAndSiteLifecycleService implements
	Service,
	Activateable,
	Deactivateable,
	SiteActivateable,
	SiteDeactivateable {

	use RecordsScopedLifecycle;

	private const NAME = 'legacy+site';
}
