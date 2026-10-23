<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

/**
 * A composition root that breaks every promise the contract checks.
 */
final class BrokenPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected function get_service_classes(): array {
		return [
			'fixture.good'          => GoodService::class,
			'fixture.resolved'      => static fn(): string => GoodService::class,
			'fixture.from-factory'  => ServiceFactory::class . '::service_class',
			'fixture.hooking'       => HookingService::class,
			'fixture.unbound'       => NeedsContract::class,
			'fixture.dependent'     => DependentService::class,
			'fixture.delayed'       => EmptyDelayedService::class,
			'fixture.not-a-service' => NotAService::class,
			'fixture.removing'      => RemovingService::class,
			'fixture.rebound'       => BoundToSomethingElse::class,
		];
	}

	protected function get_delegations(): array {
		return [
			BoundToSomethingElse::class => static fn(): NotAService => new NotAService(),
		];
	}
}
