<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class EmptyHookPrefixPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'fixture.';

	protected const HOOK_PREFIX = '';

	protected function get_service_classes(): array {
		return [
			'fixture.delayed' => TypoActionService::class,
		];
	}
}
