<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class AdminMenuPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected const HOOK_PREFIX = 'mwpd.basic_scaffold.';

	protected function get_service_classes(): array {
		return [
			'fixture.delayed' => AdminMenuService::class,
		];
	}
}
