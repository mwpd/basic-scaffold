<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\FailedToResolveDependencies;
use MWPD\BasicScaffold\Exception\InvalidService;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\DummyClass;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithDependency;
use MWPD\BasicScaffold\Tests\Fixture\DummyInterface;
use MWPD\BasicScaffold\Tests\Fixture\TestInjectorConfigurationPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestServicesPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedDependentService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceA;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceC;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceNeedingB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceNeedingUnneeded;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestUnneededDelayedService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestUnneededService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestUnneededServiceNeedingUnneeded;
use MWPD\BasicScaffold\Tests\Fixture\TestCircularDependencyPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestDependencyCheckingPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestMissingDependencyPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestMultipleDelayedDependenciesPlugin;

/**
 * Test the service-based plugin infrastructure.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin
 */
class ServiceBasedPluginTest extends TestCase {

	/**
	 * Actions the tests fire, with the callbacks added to them.
	 *
	 * Brain Monkey records added callbacks but never runs them, so the tests
	 * capture them here and run them in fire().
	 */
	private const FIRED_ACTIONS = [ 'init', 'wp_loaded', 'wp' ];

	/**
	 * Filters that configure the injector.
	 */
	private const INJECTOR_FILTERS = [
		ServiceBasedPlugin::BINDINGS_FILTER,
		ServiceBasedPlugin::ARGUMENTS_FILTER,
		ServiceBasedPlugin::SHARED_INSTANCES_FILTER,
		ServiceBasedPlugin::DELEGATIONS_FILTER,
	];

	/**
	 * @var array<string,array<int,list<callable>>>
	 */
	private array $callbacks = [];

	protected function set_up() {
		parent::set_up();

		$this->callbacks = [];

		foreach ( self::FIRED_ACTIONS as $action ) {
			Actions\expectAdded( $action )
				->zeroOrMoreTimes()
				->whenHappen(
					function ( callable $callback, int $priority ) use ( $action ): void {
						$this->callbacks[ $action ][ $priority ][] = $callback;
					}
				);
		}
	}

	/**
	 * Fire an action the way WordPress does: count it as done, then run its
	 * callbacks by priority, including those added while it runs.
	 */
	private function fire( string $action ): void {
		do_action( $action );

		$ran     = [];
		$current = PHP_INT_MIN;

		while ( true ) {
			$next = null;

			foreach ( $this->callbacks[ $action ] ?? [] as $priority => $callbacks ) {
				// Like WordPress, skip callbacks added below the running priority.
				if ( $priority < $current || ( null !== $next && $priority >= $next[0] ) ) {
					continue;
				}

				foreach ( $callbacks as $index => $callback ) {
					if ( ! isset( $ran[ "{$priority}:{$index}" ] ) ) {
						$next = [ $priority, $index, $callback ];
						break;
					}
				}
			}

			if ( null === $next ) {
				return;
			}

			[ $current, $index, $callback ] = $next;
			$ran[ "{$current}:{$index}" ]   = true;
			$callback();
		}
	}

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
	 * Test that a service listed before its dependency does not cause any
	 * service to be skipped.
	 */
	public function test_a_service_before_its_dependency_skips_no_service(): void {
		$plugin = new TestServicesPlugin(
			[
				'needs_b'   => TestServiceNeedingB::class,
				'service_b' => TestServiceB::class,
				'service_c' => TestServiceA::class,
			]
		);
		$plugin->register_services();

		$container = $plugin->get_container();

		$this->assertTrue( $container->has( 'needs_b' ) );
		$this->assertTrue( $container->has( 'service_b' ) );
		$this->assertTrue( $container->has( 'service_c' ) );
	}

	/**
	 * Test that only services with dependencies have their dependencies
	 * checked, so an override of dependencies_are_met() may rely on it.
	 */
	public function test_only_services_with_dependencies_are_checked(): void {
		$plugin = new TestDependencyCheckingPlugin();
		$plugin->register_services();

		$container = $plugin->get_container();

		$this->assertTrue( $container->has( 'needs_b' ) );
		$this->assertTrue( $container->has( 'service_a' ) );
		$this->assertTrue( $container->has( 'service_b' ) );
	}

	/**
	 * Test that delayed dependencies are properly handled.
	 */
	public function test_delayed_dependencies_are_properly_handled(): void {
		$plugin = new TestServiceBasedPlugin();
		$plugin->register();
		$this->assertNotFalse( has_action( 'plugins_loaded', [ $plugin, 'register_services' ] ) );

		do_action( 'plugins_loaded' );
		$plugin->register_services();

		// Before init, delayed service should not be registered yet.
		$this->assertFalse( $plugin->get_container()->has( 'delayed_service' ) );
		$this->assertFalse( $plugin->get_container()->has( 'dependent_service' ) );
		$this->assertTrue( has_action( 'init' ) );

		$this->fire( 'init' );

		// After init, delayed service should be registered now.
		$this->assertTrue( $plugin->get_container()->has( 'delayed_service' ) );
		$this->assertTrue( $plugin->get_container()->has( 'dependent_service' ) );
	}

	/**
	 * Test that a delayed service waits for its own registration action, even
	 * when its delayed dependency arrives earlier.
	 */
	public function test_a_delayed_dependent_waits_for_its_own_registration_action(): void {
		$plugin = new TestServicesPlugin(
			[
				'dependent_service' => TestDelayedDependentService::class,
				'delayed_service'   => TestDelayedService::class,
			]
		);
		$plugin->register_services();

		$this->fire( 'init' );

		$this->assertTrue( $plugin->get_container()->has( 'delayed_service' ) );
		$this->assertFalse( $plugin->get_container()->has( 'dependent_service' ) );

		$this->fire( 'wp' );

		$this->assertTrue( $plugin->get_container()->has( 'dependent_service' ) );
	}

	/**
	 * Test that circular dependencies are detected.
	 */
	public function test_circular_dependencies_are_detected(): void {
		$plugin = new TestCircularDependencyPlugin();

		$this->expectException( FailedToResolveDependencies::class );
		$this->expectExceptionMessage( '"circular_a" -> "circular_b" -> "circular_a"' );

		$plugin->register_services();
	}

	/**
	 * Test that depending on a service that is not needed is reported.
	 */
	public function test_depending_on_a_service_that_is_not_needed_is_reported(): void {
		$plugin = new TestServicesPlugin(
			[
				'dependent_service' => TestServiceNeedingUnneeded::class,
				'unneeded_service'  => TestUnneededService::class,
			]
		);

		$this->expectException( FailedToResolveDependencies::class );
		$this->expectExceptionMessage( '"dependent_service" depends on the service "unneeded_service"' );

		$plugin->register_services();
	}

	/**
	 * Test that depending on a delayed service that turns out not to be needed
	 * is reported once its registration action has run.
	 */
	public function test_depending_on_a_delayed_service_that_is_not_needed_is_reported(): void {
		$plugin = new TestServicesPlugin(
			[
				'dependent_service' => TestServiceNeedingUnneeded::class,
				'unneeded_service'  => TestUnneededDelayedService::class,
			]
		);
		$plugin->register_services();

		$this->expectException( FailedToResolveDependencies::class );
		$this->expectExceptionMessage( '"dependent_service" depends on the service "unneeded_service"' );

		$this->fire( 'init' );
	}

	/**
	 * Test that a service that is not needed either may depend on a service
	 * that is not needed.
	 */
	public function test_a_service_that_is_not_needed_may_depend_on_one_that_is_not_needed(): void {
		$plugin = new TestServicesPlugin(
			[
				'dependent_service' => TestUnneededServiceNeedingUnneeded::class,
				'unneeded_service'  => TestUnneededService::class,
				'service_a'         => TestServiceA::class,
			]
		);
		$plugin->register_services();

		$container = $plugin->get_container();

		$this->assertFalse( $container->has( 'dependent_service' ) );
		$this->assertFalse( $container->has( 'unneeded_service' ) );
		$this->assertTrue( $container->has( 'service_a' ) );
	}

	/**
	 * Test that missing dependencies throw an exception.
	 */
	public function test_missing_dependencies_throw_exception(): void {
		$this->expectException( InvalidService::class );

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

		// First delayed dependency registers now.
		$this->fire( 'init' );
		$this->assertTrue( $plugin->get_container()->has( 'delayed_service_1' ) );
		$this->assertFalse( $plugin->get_container()->has( 'delayed_service_2' ) );
		$this->assertFalse( $plugin->get_container()->has( 'dependent_service' ) );

		// Second delayed dependency registers now.
		$this->fire( 'wp_loaded' );
		$this->assertTrue( $plugin->get_container()->has( 'delayed_service_2' ) );
		$this->assertTrue( $plugin->get_container()->has( 'dependent_service' ) );
	}

	/**
	 * Get the injector that a plugin registered as its first service.
	 */
	private function get_registered_injector( ServiceBasedPlugin $plugin ): Injector {
		$injector = $plugin->get_container()->get( ServiceBasedPlugin::INJECTOR_ID );
		$this->assertInstanceOf( Injector::class, $injector );

		return $injector;
	}

	/**
	 * Test that a binding target may be a callable, which receives the
	 * injector and the service container.
	 */
	public function test_a_binding_target_may_be_a_callable(): void {
		$plugin = new TestInjectorConfigurationPlugin(
			[ DummyInterface::class => static fn(): string => DummyClassWithDependency::class ]
		);
		$plugin->register_services();

		$this->assertInstanceOf(
			DummyClassWithDependency::class,
			$this->get_registered_injector( $plugin )->make( DummyInterface::class )
		);
	}

	/**
	 * Test that a shared-instance entry may be a callable, which receives the
	 * injector and the service container.
	 */
	public function test_a_shared_instance_entry_may_be_a_callable(): void {
		$plugin = new TestInjectorConfigurationPlugin(
			[],
			[ static fn(): string => DummyClass::class ]
		);
		$plugin->register_services();

		$injector = $this->get_registered_injector( $plugin );

		$this->assertSame( $injector->make( DummyClass::class ), $injector->make( DummyClass::class ) );
	}

	/**
	 * Test that constructing the plugin applies none of the injector filters,
	 * and that registering the services applies each of them once.
	 */
	public function test_the_injector_filters_are_applied_during_registration(): void {
		$plugin = new TestInjectorConfigurationPlugin();

		foreach ( self::INJECTOR_FILTERS as $filter ) {
			$this->assertSame( 0, Filters\applied( $filter ), "The \"{$filter}\" filter ran during construction." );
		}

		$plugin->register_services();

		foreach ( self::INJECTOR_FILTERS as $filter ) {
			$this->assertSame(
				1,
				Filters\applied( $filter ),
				"The \"{$filter}\" filter did not run once during registration."
			);
		}
	}

	/**
	 * Test that a filter callback added after the plugin was constructed, but
	 * before the services are registered at plugins_loaded, takes effect.
	 */
	public function test_an_injector_filter_added_after_construction_takes_effect(): void {
		$plugin = new TestInjectorConfigurationPlugin();
		$plugin->register();

		// A plugin loaded later hooks in. Brain Monkey stands in for the
		// callback by returning what it would.
		Filters\expectApplied( ServiceBasedPlugin::BINDINGS_FILTER )
			->once()
			->andReturnUsing(
				static fn( array $bindings ): array => $bindings
					+ [ DummyInterface::class => DummyClassWithDependency::class ]
			);

		// What WordPress does at plugins_loaded.
		$this->assertNotFalse( has_action( 'plugins_loaded', [ $plugin, 'register_services' ] ) );
		do_action( 'plugins_loaded' );
		$plugin->register_services();

		$this->assertInstanceOf(
			DummyClassWithDependency::class,
			$this->get_registered_injector( $plugin )->make( DummyInterface::class )
		);
	}

	/**
	 * Test that activation configures the injector when plugins_loaded has
	 * already fired, as it has in the request that activates a plugin.
	 */
	public function test_activation_configures_the_injector_after_plugins_loaded(): void {
		Functions\expect( 'flush_rewrite_rules' )->once();

		$plugin = new TestInjectorConfigurationPlugin(
			[ DummyInterface::class => static fn(): string => DummyClassWithDependency::class ]
		);
		do_action( 'plugins_loaded' );

		$plugin->activate();

		$this->assertInstanceOf(
			DummyClassWithDependency::class,
			$this->get_registered_injector( $plugin )->make( DummyInterface::class )
		);

		foreach ( self::INJECTOR_FILTERS as $filter ) {
			$this->assertSame( 1, Filters\applied( $filter ) );
		}
	}

	/**
	 * Test that registering the services a second time does not configure the
	 * injector again.
	 */
	public function test_registering_twice_configures_the_injector_once(): void {
		$plugin = new TestInjectorConfigurationPlugin();
		$plugin->register_services();
		$plugin->register_services();

		foreach ( self::INJECTOR_FILTERS as $filter ) {
			$this->assertSame( 1, Filters\applied( $filter ) );
		}
	}
}
