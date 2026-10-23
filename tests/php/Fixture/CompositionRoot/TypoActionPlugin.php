<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

final class TypoActionPlugin extends ServiceBasedPlugin {

	protected const HOOK_PREFIX = 'mwpd.basic_scaffold.';

	protected function get_service_classes(): array {
		return [
			'delayed' => TypoActionService::class,
		];
	}
}
