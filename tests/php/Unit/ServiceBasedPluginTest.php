<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\FailedToMakeInstance;
use MWPD\BasicScaffold\Exception\FailedToResolveDependencies;
use MWPD\BasicScaffold\Exception\InvalidConfiguration;
use MWPD\BasicScaffold\Exception\InvalidService;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\SimpleServiceContainer;
use MWPD\BasicScaffold\Tests\Fixture\DummyClass;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithCallback;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithDependency;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithFunctionNamedArguments;
use MWPD\BasicScaffold\Tests\Fixture\DummyInterface;
use MWPD\BasicScaffold\Tests\Fixture\ImmutableInjector;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\DelayedLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\DependentLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LazyLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LifecycleRecorder;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\NeededLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\RewriteLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\UnneededDelayedLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\UnneededLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Resolver;
use MWPD\BasicScaffold\Tests\Fixture\TestInjectorConfigurationPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\TestServicesPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedDependentService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceA;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceC;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceNeedingB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceNeedingInjector;
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
	 * Binding targets in each form deferred resolution accepts, and a plain
	 * class name that it leaves alone.
	 *
	 * @return array<string,array{0:mixed}>
	 */
	public static function data_binding_targets(): array {
		return [
			'class name'           => [ DummyClassWithDependency::class ],
			'closure'              => [
				static function ( Injector $injector, ServiceContainer $container ): string {
					Resolver::record( $injector, $container );

					return DummyClassWithDependency::class;
				},
			],
			'static method string' => [ Resolver::class . '::resolve_statically' ],
			'callable array'       => [ [ new Resolver( DummyClassWithDependency::class ), 'resolve' ] ],
			'invokable object'     => [ new Resolver( DummyClassWithDependency::class ) ],
		];
	}

	/**
	 * Test that a binding target is resolved with the injector and the service
	 * container in every callable form other than a function name, and used as
	 * it is when it is not callable.
	 *
	 * @dataProvider data_binding_targets
	 *
	 * @param mixed $target Binding target.
	 */
	public function test_a_binding_target_resolves_in_every_supported_form( $target ): void {
		Resolver::$calls = [];

		/** @var class-string|callable $target */
		$plugin = new TestInjectorConfigurationPlugin( [ DummyInterface::class => $target ] );
		$plugin->register_services();

		$injector = $this->get_registered_injector( $plugin );

		$this->assertInstanceOf( DummyClassWithDependency::class, $injector->make( DummyInterface::class ) );
		$this->assertSame(
			is_string( $target ) && ! is_callable( $target ) ? [] : [ [ $injector, $plugin->get_container() ] ],
			Resolver::$calls
		);
	}

	/**
	 * Ways to spell the name of a function, which PHP all accepts as callable.
	 *
	 * @return array<string,array{0:string,1:string}> Function to define, and
	 *                                                how the configuration
	 *                                                names it.
	 */
	public static function data_function_names(): array {
		return [
			'global function'   => [ 'mwpd_basic_scaffold_test_resolver', 'mwpd_basic_scaffold_test_resolver' ],
			'leading backslash' => [ 'mwpd_basic_scaffold_test_resolver', '\\mwpd_basic_scaffold_test_resolver' ],
			'other case'        => [ 'mwpd_basic_scaffold_test_resolver', 'MWPD_Basic_Scaffold_Test_Resolver' ],
			'namespaced'        => [ __NAMESPACE__ . '\\test_resolver', __NAMESPACE__ . '\\test_resolver' ],
		];
	}

	/**
	 * Test that a string naming a function is a literal value, not a resolver:
	 * the function never runs, and the string is validated like any other
	 * class name.
	 *
	 * @dataProvider data_function_names
	 *
	 * @param string $defined Function to define.
	 * @param string $name    How the configuration names it.
	 */
	public function test_a_function_name_is_not_called_as_a_resolver( string $defined, string $name ): void {
		Functions\expect( $defined )->never();

		/**
		 * Brain Monkey has just defined the function, so the name is callable.
		 *
		 * @var callable $function_name
		 */
		$function_name = $name;

		$plugin = new TestInjectorConfigurationPlugin( [ DummyInterface::class => $function_name ] );
		$plugin->register_services();

		$this->expectException( FailedToMakeInstance::class );
		$this->expectExceptionMessage( ltrim( $name, '\\' ) );

		$this->get_registered_injector( $plugin )->make( DummyInterface::class );
	}

	/**
	 * Test that argument names which are also function names are bound as the
	 * names they are, without calling the functions.
	 */
	public function test_argument_names_that_name_functions_stay_literal(): void {
		$arguments = [
			'key'  => 'a key',
			'date' => 'a date',
			'trim' => 'a trim',
		];

		$plugin = new TestInjectorConfigurationPlugin(
			[],
			[],
			[ DummyClassWithFunctionNamedArguments::class => $arguments ]
		);
		$plugin->register_services();

		$object = $this->get_registered_injector( $plugin )->make( DummyClassWithFunctionNamedArguments::class );

		$this->assertInstanceOf( DummyClassWithFunctionNamedArguments::class, $object );
		$this->assertSame( $arguments, $object->get_arguments() );
	}

	/**
	 * Test that a callable argument value reaches the constructor as it is,
	 * including a function name, instead of being called.
	 */
	public function test_a_callable_argument_value_is_passed_as_is(): void {
		$closure = static fn(): string => 'called';

		foreach ( [ $closure, 'trim' ] as $callback ) {
			$plugin = new TestInjectorConfigurationPlugin(
				[],
				[],
				[ DummyClassWithCallback::class => [ 'callback' => $callback ] ]
			);
			$plugin->register_services();

			$object = $this->get_registered_injector( $plugin )->make( DummyClassWithCallback::class );

			$this->assertInstanceOf( DummyClassWithCallback::class, $object );
			$this->assertSame( $callback, $object->get_callback() );
		}
	}

	/**
	 * Test that a delegation factory is handed to the injector as it is, as a
	 * closure or as a function name, and runs only when the injector makes the
	 * class.
	 */
	public function test_a_delegation_factory_is_passed_as_is(): void {
		$made = new DummyClassWithDependency( new DummyClass() );

		Functions\expect( 'mwpd_basic_scaffold_test_factory' )
			->once()
			->with( DummyInterface::class )
			->andReturn( $made );

		/**
		 * Brain Monkey has just defined the function, so the name is callable.
		 *
		 * @var list<callable> $factories
		 */
		$factories = [
			static fn(): DummyClassWithDependency => $made,
			'mwpd_basic_scaffold_test_factory',
		];

		foreach ( $factories as $factory ) {
			$plugin = new TestInjectorConfigurationPlugin( [], [], [], [ DummyInterface::class => $factory ] );
			$plugin->register_services();

			$this->assertSame( $made, $this->get_registered_injector( $plugin )->make( DummyInterface::class ) );
		}
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

	/**
	 * Test that a service whose constructor takes the Injector interface
	 * receives the injector the plugin itself uses.
	 */
	public function test_a_service_can_receive_the_plugin_injector(): void {
		$plugin = new TestServicesPlugin( [ 'needs_injector' => TestServiceNeedingInjector::class ] );
		$plugin->register_services();

		$service = $plugin->get_container()->get( 'needs_injector' );

		$this->assertInstanceOf( TestServiceNeedingInjector::class, $service );
		$this->assertSame( $this->get_registered_injector( $plugin ), $service->get_injector() );
	}

	/**
	 * Test that a custom injector passed to the plugin is the one that gets
	 * injected, both into services and into objects made on demand.
	 */
	public function test_a_custom_injector_is_the_one_injected(): void {
		$injector = new SimpleInjector();
		$plugin   = new TestServicesPlugin(
			[ 'needs_injector' => TestServiceNeedingInjector::class ],
			$injector
		);
		$plugin->register_services();

		$service = $plugin->get_container()->get( 'needs_injector' );

		$this->assertInstanceOf( TestServiceNeedingInjector::class, $service );
		$this->assertSame( $injector, $service->get_injector() );
		$this->assertSame( $injector, $injector->make( Injector::class ) );
	}

	/**
	 * Test that an injector whose configuration methods return a configured
	 * copy is handled: the copy is the injector the plugin keeps, and the one
	 * that gets injected.
	 */
	public function test_an_injector_that_returns_copies_injects_the_configured_copy(): void {
		$original = new ImmutableInjector();
		$plugin   = new TestServicesPlugin(
			[ 'needs_injector' => TestServiceNeedingInjector::class ],
			$original
		);
		$plugin->register_services();

		$injector = $this->get_registered_injector( $plugin );
		$service  = $plugin->get_container()->get( 'needs_injector' );

		$this->assertNotSame( $original, $injector );
		$this->assertInstanceOf( TestServiceNeedingInjector::class, $service );
		$this->assertSame( $injector, $service->get_injector() );
	}

	/**
	 * Test that a services filter that returns something other than an array
	 * is reported as invalid configuration, not as a PHP warning.
	 */
	public function test_a_services_filter_that_returns_no_array_throws(): void {
		Filters\expectApplied( ServiceBasedPlugin::SERVICES_FILTER )
			->once()
			->andReturn( 'not an array' );

		$plugin = new TestServiceBasedPlugin();

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage( 'The services configuration "not an array" is not an array' );

		$plugin->register_services();
	}

	/**
	 * Test that a service whose class does not exist is reported when the
	 * services are registered, naming the service.
	 */
	public function test_a_service_class_that_does_not_exist_throws(): void {
		/**
		 * A class name that no autoloader can load.
		 *
		 * @var class-string $missing
		 */
		$missing = __NAMESPACE__ . '\\NoSuchService';

		$plugin = new TestServicesPlugin( [ 'missing' => $missing ] );

		$this->expectException( InvalidService::class );
		$this->expectExceptionMessage(
			'The service "missing" names the class "' . $missing . '", which does not exist.'
		);

		$plugin->register_services();
	}

	/**
	 * Test that a service whose class does not implement Service is reported
	 * when the services are registered, even when it is not Registerable and
	 * would otherwise only fail once it is first retrieved.
	 */
	public function test_a_service_class_that_is_not_a_service_throws(): void {
		$plugin = new TestServicesPlugin( [ 'not_a_service' => DummyClass::class ] );

		$this->expectException( InvalidService::class );
		$this->expectExceptionMessage(
			'The service "not_a_service" names the class "' . DummyClass::class . '", which does not implement Service.'
		);

		$plugin->register_services();
	}

	/**
	 * Test that a binding whose implementation cannot stand in for what it is
	 * bound to is reported when the injector is configured.
	 */
	public function test_a_binding_to_a_type_that_is_not_a_subtype_throws(): void {
		$plugin = new TestInjectorConfigurationPlugin( [ DummyInterface::class => DummyClass::class ] );

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage(
			'"' . DummyInterface::class . '" cannot be bound to "' . DummyClass::class . '"'
		);

		$plugin->register_services();
	}

	/**
	 * Test that a binding to a class implementing the bound interface is
	 * accepted.
	 */
	public function test_a_binding_to_a_subtype_is_accepted(): void {
		$plugin = new TestInjectorConfigurationPlugin( [ DummyInterface::class => DummyClassWithDependency::class ] );
		$plugin->register_services();

		$this->assertInstanceOf(
			DummyClassWithDependency::class,
			$this->get_registered_injector( $plugin )->make( DummyInterface::class )
		);
	}

	/**
	 * Build a plugin whose services all receive the same recorder.
	 *
	 * @param array<string,class-string> $services Services, in configuration order.
	 */
	private function make_lifecycle_plugin( array $services, LifecycleRecorder $recorder ): TestServicesPlugin {
		return new TestServicesPlugin(
			$services,
			( new SimpleInjector() )->delegate( LifecycleRecorder::class, static fn(): LifecycleRecorder => $recorder )
		);
	}

	/**
	 * Expect exactly one soft flush of the rewrite rules, and record it.
	 */
	private function expect_one_soft_flush( LifecycleRecorder $recorder ): void {
		Functions\expect( 'flush_rewrite_rules' )
			->once()
			->with( false )
			->andReturnUsing(
				static function () use ( $recorder ): void {
					$recorder->record( 'flush' );
				}
			);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function data_lifecycle_events(): array {
		return [
			'activation'   => [ 'activate' ],
			'deactivation' => [ 'deactivate' ],
		];
	}

	/**
	 * Test that a service that is not Registerable, and therefore stored
	 * lazily, receives the lifecycle call.
	 *
	 * @dataProvider data_lifecycle_events
	 */
	public function test_a_lazily_stored_service_receives_the_lifecycle_call( string $event ): void {
		$recorder = new LifecycleRecorder();
		$this->expect_one_soft_flush( $recorder );
		$plugin = $this->make_lifecycle_plugin( [ 'lazy' => LazyLifecycleService::class ], $recorder );

		$plugin->$event();

		$this->assertSame( [ 'construct:lazy', "{$event}:lazy", 'flush' ], $recorder->events() );
	}

	/**
	 * Test that a delayed service whose registration action has not fired
	 * receives the lifecycle call, without being registered early, and is
	 * registered once its action fires.
	 *
	 * @dataProvider data_lifecycle_events
	 */
	public function test_a_delayed_service_receives_the_lifecycle_call_before_its_action( string $event ): void {
		$recorder = new LifecycleRecorder();
		$this->expect_one_soft_flush( $recorder );
		$plugin = $this->make_lifecycle_plugin( [ 'delayed' => DelayedLifecycleService::class ], $recorder );

		$plugin->$event();

		$this->assertSame( [ 'construct:delayed', "{$event}:delayed", 'flush' ], $recorder->events() );
		$this->assertFalse( $plugin->get_container()->has( 'delayed' ) );

		$this->fire( 'init' );

		$this->assertTrue( $plugin->get_container()->has( 'delayed' ) );
		$this->assertSame(
			[ 'construct:delayed', "{$event}:delayed", 'flush', 'construct:delayed', 'register:delayed' ],
			$recorder->events()
		);
	}

	/**
	 * Test that a Conditional service that is not needed is neither built nor
	 * called, while needed and unconditional services receive the call.
	 *
	 * @dataProvider data_lifecycle_events
	 */
	public function test_only_needed_services_receive_the_lifecycle_call( string $event ): void {
		$recorder = new LifecycleRecorder();
		$this->expect_one_soft_flush( $recorder );
		$plugin = $this->make_lifecycle_plugin(
			[
				'unneeded'         => UnneededLifecycleService::class,
				'unneeded-delayed' => UnneededDelayedLifecycleService::class,
				'needed'           => NeededLifecycleService::class,
				'lazy'             => LazyLifecycleService::class,
			],
			$recorder
		);

		$plugin->$event();

		$this->assertSame(
			[ 'construct:needed', "{$event}:needed", 'construct:lazy', "{$event}:lazy", 'flush' ],
			$recorder->events()
		);
	}

	/**
	 * Test that HasDependencies neither gates nor orders the lifecycle calls:
	 * a service waiting for a delayed dependency is called in configuration
	 * order, before that dependency, and neither is registered early. Its
	 * constructor still receives its collaborator from the injector.
	 *
	 * @dataProvider data_lifecycle_events
	 */
	public function test_dependencies_do_not_gate_or_order_the_lifecycle_calls( string $event ): void {
		$recorder = new LifecycleRecorder();
		$this->expect_one_soft_flush( $recorder );
		$plugin = $this->make_lifecycle_plugin(
			[
				'dependent' => DependentLifecycleService::class,
				'delayed'   => DelayedLifecycleService::class,
			],
			$recorder
		);

		$plugin->$event();

		$this->assertSame(
			[ 'construct:dependent', "{$event}:dependent", 'construct:delayed', "{$event}:delayed", 'flush' ],
			$recorder->events()
		);
		$this->assertFalse( $plugin->get_container()->has( 'dependent' ) );
		$this->assertFalse( $plugin->get_container()->has( 'delayed' ) );

		$this->fire( 'init' );

		$this->assertTrue( $plugin->get_container()->has( 'delayed' ) );
		$this->assertTrue( $plugin->get_container()->has( 'dependent' ) );
	}

	/**
	 * Test that a service already registered in the request receives the call
	 * on the instance in the container, rather than on a new one.
	 */
	public function test_a_registered_service_is_activated_on_its_registered_instance(): void {
		$recorder = new LifecycleRecorder();
		$this->expect_one_soft_flush( $recorder );
		$plugin = $this->make_lifecycle_plugin( [ 'delayed' => DelayedLifecycleService::class ], $recorder );
		$plugin->register_services();
		$this->fire( 'init' );

		$plugin->activate();

		$this->assertSame(
			[ 'construct:delayed', 'register:delayed', 'activate:delayed', 'flush' ],
			$recorder->events()
		);
	}

	/**
	 * Test that a service in a container passed in already filled still
	 * receives the lifecycle call, as it did before the services were
	 * selected by class.
	 *
	 * @dataProvider data_lifecycle_events
	 */
	public function test_a_service_in_a_prefilled_container_receives_the_lifecycle_call( string $event ): void {
		$recorder = new LifecycleRecorder();
		$this->expect_one_soft_flush( $recorder );
		$container = new SimpleServiceContainer();
		$container->put( 'lazy', new LazyLifecycleService( $recorder ) );
		$plugin = new TestServicesPlugin( [], null, $container );

		$plugin->$event();

		$this->assertSame( [ 'construct:lazy', "{$event}:lazy", 'flush' ], $recorder->events() );
	}

	/**
	 * Test that a rewrite-producing service sets up its post type in
	 * activate() and removes it in deactivate(), both before the plugin
	 * requests the soft flush, without its register() running early.
	 */
	public function test_a_rewrite_service_sets_up_and_tears_down_before_the_flush(): void {
		$recorder = new LifecycleRecorder();
		Functions\expect( 'flush_rewrite_rules' )
			->twice()
			->with( false )
			->andReturnUsing(
				static function () use ( $recorder ): void {
					$recorder->record( 'flush' );
				}
			);
		Functions\expect( 'register_post_type' )
			->once()
			->with( RewriteLifecycleService::POST_TYPE, \Mockery::type( 'array' ) )
			->andReturnUsing(
				static function () use ( $recorder ): void {
					$recorder->record( 'register_post_type' );
				}
			);
		Functions\expect( 'unregister_post_type' )
			->once()
			->with( RewriteLifecycleService::POST_TYPE )
			->andReturnUsing(
				static function () use ( $recorder ): bool {
					$recorder->record( 'unregister_post_type' );
					return true;
				}
			);
		$plugin = $this->make_lifecycle_plugin( [ 'rewrite' => RewriteLifecycleService::class ], $recorder );

		$plugin->activate();
		$plugin->deactivate();

		$this->assertSame(
			[ 'register_post_type', 'flush', 'unregister_post_type', 'flush' ],
			$recorder->events()
		);
		$this->assertFalse( $plugin->get_container()->has( 'rewrite' ) );
	}
}
