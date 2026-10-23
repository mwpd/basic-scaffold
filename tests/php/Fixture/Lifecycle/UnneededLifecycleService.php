<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class UnneededLifecycleService implements Service, Conditional, Activateable, Deactivateable {
	use RecordsLifecycle;

	private const NAME = 'unneeded';

	public static function is_needed(): bool {
		return false;
	}
}
