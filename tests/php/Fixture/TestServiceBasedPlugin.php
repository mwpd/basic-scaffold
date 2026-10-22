<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceA;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceC;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDependentService;

class TestServiceBasedPlugin extends ServiceBasedPlugin {
	protected function get_service_classes(): array {
		return [
			'service_a'         => TestServiceA::class,
			'service_b'         => TestServiceB::class,
			'service_c'         => TestServiceC::class,
			'delayed_service'   => TestDelayedService::class,
			'dependent_service' => TestDependentService::class,
		];
	}
}
