<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class SampleServiceIdPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'generated.plugin.';

	protected function get_service_classes(): array {
		return [
			'mwpd.basic_scaffold.service' => GoodService::class,
		];
	}
}
