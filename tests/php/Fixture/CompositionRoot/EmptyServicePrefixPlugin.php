<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class EmptyServicePrefixPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = '';

	protected function get_service_classes(): array {
		return [
			'fixture.service' => GoodService::class,
		];
	}
}
