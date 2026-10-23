<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class OverlappingCyclesPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected function get_service_classes(): array {
		return [
			'fixture.a' => DependsOnBAndC::class,
			'fixture.b' => DependsOnC::class,
			'fixture.c' => DependsOnA::class,
		];
	}
}
