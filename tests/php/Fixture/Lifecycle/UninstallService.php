<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\NetworkUninstallable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;

/**
 * Cleans up each site and each network on uninstall.
 */
final class UninstallService implements Service, SiteUninstallable, NetworkUninstallable {
	use RecordsScopedLifecycle;

	private const NAME = 'uninstall';
}
