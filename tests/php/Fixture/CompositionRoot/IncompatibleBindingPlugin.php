<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

/**
 * A composition root whose injector configuration the plugin rejects, so none
 * of its services can be built.
 */
final class IncompatibleBindingPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected function get_service_classes(): array {
		return [
			'fixture.good' => GoodService::class,
		];
	}

	protected function get_bindings(): array {
		return [
			BoundToSomethingElse::class => NotAService::class,
		];
	}
}
