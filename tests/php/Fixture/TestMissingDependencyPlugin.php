<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceWithMissingDependency;

class TestMissingDependencyPlugin extends ServiceBasedPlugin {
	protected function get_service_classes(): array {
		return [
			'service_with_missing' => TestServiceWithMissingDependency::class,
		];
	}
}
