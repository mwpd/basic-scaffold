<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class OverlappingCyclesPlugin extends ServiceBasedPlugin {

	protected function get_service_classes(): array {
		return [
			'a' => DependsOnBAndC::class,
			'b' => DependsOnC::class,
			'c' => DependsOnA::class,
		];
	}
}
