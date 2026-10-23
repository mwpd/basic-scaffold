<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class MisorderedDependencyPlugin extends ServiceBasedPlugin {

	protected function get_service_classes(): array {
		return [
			'a' => DependsOnB::class,
			'b' => static fn(): string => WpService::class,
		];
	}
}
