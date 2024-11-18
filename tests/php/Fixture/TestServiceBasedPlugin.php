<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

class TestServiceBasedPlugin extends ServiceBasedPlugin {
    protected function get_service_classes(): array {
        return [
            'service_a' => TestServiceA::class,
            'service_b' => TestServiceB::class,
            'service_c' => TestServiceC::class,
            'delayed_service' => TestDelayedService::class,
            'dependent_service' => TestDependentService::class,
        ];
    }
} 