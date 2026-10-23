<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class UnknownActionDependencyPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected const HOOK_PREFIX = 'fixture.';

	protected function get_service_classes(): array {
		return [
			'fixture.a'     => DependsOnB::class,
			'fixture.b'     => CustomActionService::class,
			'fixture.later' => WpDependentService::class,
		];
	}
}
