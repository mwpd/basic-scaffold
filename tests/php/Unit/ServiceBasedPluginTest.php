<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Tests\Fixture\TestServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestServiceA;
use MWPD\BasicScaffold\Tests\Fixture\TestServiceB;
use MWPD\BasicScaffold\Tests\Fixture\TestServiceC;
use MWPD\BasicScaffold\Tests\Fixture\TestCircularDependencyPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestMissingDependencyPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestMultipleDelayedDependenciesPlugin;

/**
 * Test the service-based plugin infrastructure.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin
 */
class ServiceBasedPluginTest extends TestCase {

    /**
     * Test that dependencies are properly resolved.
     */
    public function test_dependencies_are_properly_resolved(): void {
        $plugin = new TestServiceBasedPlugin(false);
        $plugin->register();
        do_action('plugins_loaded');

        $container = $plugin->get_container();

        $this->assertTrue($container->has('service_a'));
        $this->assertTrue($container->has('service_b'));
        $this->assertTrue($container->has('service_c'));

        $this->assertInstanceOf(TestServiceA::class, $container->get('service_a'));
        $this->assertInstanceOf(TestServiceB::class, $container->get('service_b'));
        $this->assertInstanceOf(TestServiceC::class, $container->get('service_c'));
    }

    /**
     * Test that delayed dependencies are properly handled.
     */
    public function test_delayed_dependencies_are_properly_handled(): void {
        $plugin = new TestServiceBasedPlugin(false);
        $plugin->register();
        
        // Before init, delayed service should not be registered
        $this->assertFalse($plugin->get_container()->has('delayed_service'));
        
        do_action('init');
        
        // After init, delayed service should be registered
        $this->assertTrue($plugin->get_container()->has('delayed_service'));
        $this->assertTrue($plugin->get_container()->has('dependent_service'));
    }

    /**
     * Test that circular dependencies are detected.
     */
    public function test_circular_dependencies_are_detected(): void {
        $plugin = new TestCircularDependencyPlugin(false);
        $plugin->register();

        // The services should not be registered due to circular dependency
        $this->assertFalse($plugin->get_container()->has('circular_a'));
        $this->assertFalse($plugin->get_container()->has('circular_b'));
    }

    /**
     * Test that missing dependencies throw an exception.
     */
    public function test_missing_dependencies_throw_exception(): void {
        $this->expectException(\MWPD\BasicScaffold\Exception\InvalidService::class);

        $plugin = new TestMissingDependencyPlugin(false);
        $plugin->register();
        do_action('plugins_loaded');
    }

    /**
     * Test that multiple delayed dependencies are handled correctly.
     */
    public function test_multiple_delayed_dependencies(): void {
        $plugin = new TestMultipleDelayedDependenciesPlugin(false);
        $plugin->register();

        $this->assertFalse($plugin->get_container()->has('dependent_service'));
        
        // First delayed dependency registers
        do_action('init');
        $this->assertFalse($plugin->get_container()->has('dependent_service'));
        
        // Second delayed dependency registers
        do_action('wp_loaded');
        $this->assertTrue($plugin->get_container()->has('dependent_service'));
    }
} 