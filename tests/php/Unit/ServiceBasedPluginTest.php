<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Tests\Fixture\TestServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceA;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceC;
use MWPD\BasicScaffold\Tests\Fixture\TestCircularDependencyPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestMissingDependencyPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestMultipleDelayedDependenciesPlugin;

/**
 * Test the service-based plugin infrastructure.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin
 * @phpcs:disable Squiz.Commenting.InlineComment,Squiz.PHP.CommentedOutCode -- until the tests have been fixed.
 */
class ServiceBasedPluginTest extends TestCase {

	/**
	 * Test that dependencies are properly resolved.
	 */
	public function test_dependencies_are_properly_resolved(): void {
		$plugin = new TestServiceBasedPlugin();
		$plugin->register_services();

		$container = $plugin->get_container();

		$this->assertTrue( $container->has( 'service_a' ) );
		$this->assertTrue( $container->has( 'service_b' ) );
		$this->assertTrue( $container->has( 'service_c' ) );

		$this->assertInstanceOf( TestServiceA::class, $container->get( 'service_a' ) );
		$this->assertInstanceOf( TestServiceB::class, $container->get( 'service_b' ) );
		$this->assertInstanceOf( TestServiceC::class, $container->get( 'service_c' ) );
	}

	/**
	 * Test that delayed dependencies are properly handled.
	 */
	public function test_delayed_dependencies_are_properly_handled(): void {
		$plugin = new TestServiceBasedPlugin();
		$plugin->register();
		$this->assertNotFalse( has_action( 'plugins_loaded', [ $plugin, 'register_services' ] ) );

		do_action( 'plugins_loaded' );

		// Before init, delayed service should not be registered yet.
		$this->assertFalse( $plugin->get_container()->has( 'delayed_service' ) );

		// TODO (AS): Below steps cannot be tested yet without actually executing the actions.
		// $this->assertNotFalse( has_action( 'init', 'function ()' ) );

		// do_action( 'init' );

		// After init, delayed service should be registered now.
		// $this->assertTrue( $plugin->get_container()->has( 'delayed_service' ) );
		// $this->assertTrue( $plugin->get_container()->has( 'dependent_service' ) );
	}

	/**
	 * Test that circular dependencies are detected.
	 */
	public function test_circular_dependencies_are_detected(): void {
		$plugin = new TestCircularDependencyPlugin();
		$plugin->register_services();

		// The services should not be registered due to circular dependency.
		$this->assertFalse( $plugin->get_container()->has( 'circular_a' ) );
		$this->assertFalse( $plugin->get_container()->has( 'circular_b' ) );
	}

	/**
	 * Test that missing dependencies throw an exception.
	 */
	public function test_missing_dependencies_throw_exception(): void {
		$this->expectException( \MWPD\BasicScaffold\Exception\InvalidService::class );

		$plugin = new TestMissingDependencyPlugin();
		$plugin->register_services();
	}

	/**
	 * Test that multiple delayed dependencies are handled correctly.
	 */
	public function test_multiple_delayed_dependencies(): void {
		$plugin = new TestMultipleDelayedDependenciesPlugin();
		$plugin->register_services();

		$this->assertFalse( $plugin->get_container()->has( 'delayed_service_1' ) );
		$this->assertFalse( $plugin->get_container()->has( 'delayed_service_2' ) );
		$this->assertFalse( $plugin->get_container()->has( 'dependent_service' ) );

		// TODO (AS): Below steps cannot be tested yet without actually executing the actions.

		// First delayed dependency registers now.
		// do_action( 'init' );
		// $this->assertTrue( $plugin->get_container()->has( 'delayed_service_1' ) );
		// $this->assertFalse( $plugin->get_container()->has( 'delayed_service_2' ) );
		// $this->assertFalse( $plugin->get_container()->has( 'dependent_service' ) );

		// Second delayed dependency registers now.
		// do_action( 'wp_loaded' );
		// $this->assertTrue( $plugin->get_container()->has( 'delayed_service_2' ) );
		// $this->assertTrue( $plugin->get_container()->has( 'dependent_service' ) );
	}
}
