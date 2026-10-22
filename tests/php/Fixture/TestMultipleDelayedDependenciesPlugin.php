<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService1;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService2;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestMultiDependentService;

class TestMultipleDelayedDependenciesPlugin extends ServiceBasedPlugin {
	protected function get_service_classes(): array {
		return [
			'delayed_service_1' => TestDelayedService1::class,
			'delayed_service_2' => TestDelayedService2::class,
			'dependent_service' => TestMultiDependentService::class,
		];
	}
}
