<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

class TestMultipleDelayedDependenciesPlugin extends ServiceBasedPlugin {
    protected function get_service_classes(): array {
        return [
            'delayed_service_1' => TestDelayedService1::class,
            'delayed_service_2' => TestDelayedService2::class,
            'dependent_service' => TestMultiDependentService::class,
        ];
    }
} 