<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

class TestCircularDependencyPlugin extends ServiceBasedPlugin {
    protected function get_service_classes(): array {
        return [
            'circular_a' => TestCircularA::class,
            'circular_b' => TestCircularB::class,
        ];
    }
} 