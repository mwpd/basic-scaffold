<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class BrokenLifecyclePlugin extends ServiceBasedPlugin {

	protected function get_service_classes(): array {
		return [
			'RepeatingRegistration'        => RepeatingRegistration::class,
			'ChangingPriorityRegistration' => ChangingPriorityRegistration::class,
			'AppendingActivation'          => AppendingActivation::class,
			'ChangingDeactivation'         => ChangingDeactivation::class,
			'ChangingTransient'            => ChangingTransient::class,
			'ThrowingLifecycle'            => ThrowingLifecycle::class,
		];
	}
}
