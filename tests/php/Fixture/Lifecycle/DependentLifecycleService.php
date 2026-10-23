<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * Depends on the delayed service, so it waits for init to register.
 */
final class DependentLifecycleService implements Service, Registerable, HasDependencies, Activateable, Deactivateable {
	use RecordsLifecycle;

	private const NAME = 'dependent';

	public static function get_dependencies(): array {
		return [ 'delayed' ];
	}

	public function register(): void {
		$this->recorder->record( 'register:' . self::NAME );
	}
}
