<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class SelfDependentPlugin extends ServiceBasedPlugin {

	protected function get_service_classes(): array {
		return [
			'b' => DependsOnB::class,
		];
	}
}
