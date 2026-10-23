<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * Not Registerable, so the plugin stores it lazily.
 */
final class LazyLifecycleService implements Service, Activateable, Deactivateable {
	use RecordsLifecycle;

	private const NAME = 'lazy';
}
