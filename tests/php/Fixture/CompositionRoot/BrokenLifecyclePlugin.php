<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class BrokenLifecyclePlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected function get_service_classes(): array {
		return [
			'fixture.RepeatingRegistration'        => RepeatingRegistration::class,
			'fixture.ChangingPriorityRegistration' => ChangingPriorityRegistration::class,
			'fixture.AppendingActivation'          => AppendingActivation::class,
			'fixture.ChangingDeactivation'         => ChangingDeactivation::class,
			'fixture.ChangingTransient'            => ChangingTransient::class,
			'fixture.AppendingScopedLifecycle'     => AppendingScopedLifecycle::class,
			'fixture.ThrowingLifecycle'            => ThrowingLifecycle::class,
		];
	}
}
