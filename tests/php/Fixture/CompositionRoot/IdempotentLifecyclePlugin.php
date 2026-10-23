<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class IdempotentLifecyclePlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected function get_service_classes(): array {
		return [
			'fixture.GuardedActivation'     => GuardedActivation::class,
			'fixture.SettingLifecycle'      => SettingLifecycle::class,
			'fixture.IdenticalRegistration' => IdenticalRegistration::class,
			'fixture.RemovingDeactivation'  => RemovingDeactivation::class,
			'fixture.CleaningUninstall'     => CleaningUninstall::class,
		];
	}
}
