<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * Registers on init, which has not fired in the tests' lifecycle requests.
 */
final class DelayedLifecycleService implements Service, Registerable, Delayed, Activateable, Deactivateable {
	use RecordsLifecycle;

	private const NAME = 'delayed';

	public static function get_registration_action(): string {
		return 'init';
	}

	public function register(): void {
		$this->recorder->record( 'register:' . self::NAME );
	}
}
