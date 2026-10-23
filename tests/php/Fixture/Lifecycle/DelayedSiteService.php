<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;

/**
 * Registers on init, which has not fired in the tests' lifecycle requests,
 * and is set up in each site.
 */
final class DelayedSiteService implements Service, Registerable, Delayed, SiteActivateable {
	use DefaultRegistrationPriority;
	use RecordsScopedLifecycle;

	private const NAME = 'delayed-site';

	public static function get_registration_action(): string {
		return 'init';
	}

	public function register(): void {
		$this->recorder->record( 'register:' . self::NAME );
	}
}
