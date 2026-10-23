<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class IdempotentLifecyclePlugin extends ServiceBasedPlugin {

	protected function get_service_classes(): array {
		return [
			'GuardedActivation'     => GuardedActivation::class,
			'SettingLifecycle'      => SettingLifecycle::class,
			'IdenticalRegistration' => IdenticalRegistration::class,
			'RemovingDeactivation'  => RemovingDeactivation::class,
		];
	}
}
