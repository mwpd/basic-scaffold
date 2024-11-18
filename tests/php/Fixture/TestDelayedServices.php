<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestDelayedService1 implements Service, Delayed {
    public static function get_registration_action(): string {
        return 'init';
    }
}

class TestDelayedService2 implements Service, Delayed {
    public static function get_registration_action(): string {
        return 'wp_loaded';
    }
}

class TestMultiDependentService implements Service, HasDependencies {
    public static function get_dependencies(): array {
        return ['delayed_service_1', 'delayed_service_2'];
    }
} 