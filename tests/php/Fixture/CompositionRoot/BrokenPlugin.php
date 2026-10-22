<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

/**
 * A composition root that breaks every promise the contract checks.
 */
final class BrokenPlugin extends ServiceBasedPlugin {

	protected function get_service_classes(): array {
		return [
			'good'          => GoodService::class,
			'resolved'      => static fn(): string => GoodService::class,
			'from-factory'  => ServiceFactory::class . '::service_class',
			'hooking'       => HookingService::class,
			'unbound'       => NeedsContract::class,
			'dependent'     => DependentService::class,
			'delayed'       => EmptyDelayedService::class,
			'not-a-service' => NotAService::class,
			'removing'      => RemovingService::class,
			'rebound'       => BoundToSomethingElse::class,
		];
	}

	protected function get_bindings(): array {
		return [
			BoundToSomethingElse::class => NotAService::class,
		];
	}
}
