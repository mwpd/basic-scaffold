<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

class TestServiceA implements Service {}

class TestServiceB implements Service {}

class TestServiceC implements Service, HasDependencies {
    public static function get_dependencies(): array {
        return ['service_a', 'service_b'];
    }
}

class TestDelayedService implements Service, Delayed {
    public static function get_registration_action(): string {
        return 'init';
    }
}

class TestDependentService implements Service, HasDependencies {
    public static function get_dependencies(): array {
        return ['delayed_service'];
    }
} 