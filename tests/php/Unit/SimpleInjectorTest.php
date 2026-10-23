<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Tests\Fixture\DummyClass;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithDependency;
use MWPD\BasicScaffold\Tests\Fixture\DummyInterface;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithNamedArguments;
use MWPD\BasicScaffold\Tests\Fixture\Injector\BindingCycleFrom;
use MWPD\BasicScaffold\Tests\Fixture\Injector\BindingCycleTo;
use MWPD\BasicScaffold\Tests\Fixture\Injector\CycleEnd;
use MWPD\BasicScaffold\Tests\Fixture\Injector\CycleStart;
use MWPD\BasicScaffold\Tests\Fixture\Injector\DiamondTop;
use MWPD\BasicScaffold\Tests\Fixture\Injector\SharedLeaf;
use MWPD\BasicScaffold\Tests\Fixture\Injector\TwoOfTheSameBoundDependency;
use MWPD\BasicScaffold\Tests\Fixture\Injector\TwoOfTheSameDependency;
use MWPD\BasicScaffold\Exception\FailedToMakeInstance;
use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Injector\SimpleInjector;
use MWPD\BasicScaffold\Infrastructure\Instantiator;
use stdClass;
use PHPUnit\Framework\MockObject\MockObject;
final class SimpleInjectorTest extends TestCase {

	public function test_it_can_be_initialized(): void {
		$injector = new SimpleInjector();

		$this->assertInstanceOf( SimpleInjector::class, $injector );
	}

	public function test_it_implements_the_interface(): void {
		$injector = new SimpleInjector();

		$this->assertInstanceOf( Injector::class, $injector );
	}

	public function test_it_can_instantiate_a_concrete_class(): void {
		$object = ( new SimpleInjector() )
			->make( DummyClass::class );

		$this->assertInstanceOf( DummyClass::class, $object );
	}

	public function test_it_can_autowire_a_class_with_a_dependency(): void {
		$object = ( new SimpleInjector() )
			->make( DummyClassWithDependency::class );

		$this->assertInstanceOf( DummyClassWithDependency::class, $object );
		$this->assertInstanceOf( DummyClass::class, $object->get_dummy() );
	}

	public function test_it_can_instantiate_a_bound_interface(): void {
		$injector = ( new SimpleInjector() )
			->bind(
				DummyInterface::class,
				DummyClassWithDependency::class
			);
		$object   = $injector->make( DummyInterface::class );

		$this->assertInstanceOf( DummyInterface::class, $object );
		$this->assertInstanceOf( DummyClassWithDependency::class, $object );
		$this->assertInstanceOf( DummyClass::class, $object->get_dummy() );
	}

	public function test_it_returns_separate_instances_by_default(): void {
		$injector = new SimpleInjector();
		$object_a = $injector->make( DummyClass::class );
		$object_b = $injector->make( DummyClass::class );

		$this->assertNotSame( $object_a, $object_b );
	}

	public function test_it_returns_same_instances_if_shared(): void {
		$injector = ( new SimpleInjector() )
			->share( DummyClass::class );
		$object_a = $injector->make( DummyClass::class );
		$object_b = $injector->make( DummyClass::class );

		$this->assertSame( $object_a, $object_b );
	}

	public function test_it_shares_a_bound_interface(): void {
		$injector = ( new SimpleInjector() )
			->bind( DummyInterface::class, DummyClassWithDependency::class )
			->share( DummyInterface::class );
		$object_a = $injector->make( DummyInterface::class );
		$object_b = $injector->make( DummyInterface::class );

		$this->assertInstanceOf( DummyClassWithDependency::class, $object_a );
		$this->assertSame( $object_a, $object_b );
	}

	public function test_it_shares_a_bound_interface_as_a_dependency(): void {
		$injector = ( new SimpleInjector() )
			->bind( DummyInterface::class, DummyClassWithDependency::class )
			->share( DummyInterface::class );
		$object   = $injector->make( TwoOfTheSameBoundDependency::class );

		$this->assertInstanceOf( TwoOfTheSameBoundDependency::class, $object );
		$this->assertSame( $object->first, $object->second );
		$this->assertSame( $object->first, $injector->make( DummyInterface::class ) );
	}

	public function test_sharing_a_bound_interface_leaves_its_implementation_unshared(): void {
		$injector = ( new SimpleInjector() )
			->bind( DummyInterface::class, DummyClassWithDependency::class )
			->share( DummyInterface::class );
		$shared   = $injector->make( DummyInterface::class );

		$this->assertNotSame( $shared, $injector->make( DummyClassWithDependency::class ) );
		$this->assertNotSame(
			$injector->make( DummyClassWithDependency::class ),
			$injector->make( DummyClassWithDependency::class )
		);
	}

	public function test_it_reuses_a_shared_implementation_for_a_bound_interface(): void {
		$injector       = ( new SimpleInjector() )
			->bind( DummyInterface::class, DummyClassWithDependency::class )
			->share( DummyClassWithDependency::class );
		$implementation = $injector->make( DummyClassWithDependency::class );

		$this->assertSame( $implementation, $injector->make( DummyInterface::class ) );
	}

	public function test_it_shares_an_instance_made_by_a_delegate(): void {
		$calls    = 0;
		$injector = ( new SimpleInjector() )
			->share( SharedLeaf::class )
			->delegate(
				SharedLeaf::class,
				static function () use ( &$calls ): SharedLeaf {
					++$calls;
					return new SharedLeaf();
				}
			);
		$object_a = $injector->make( SharedLeaf::class );
		$object_b = $injector->make( SharedLeaf::class );

		$this->assertSame( $object_a, $object_b );
		$this->assertSame( 1, $calls );
	}

	public function test_it_shares_an_instance_made_by_a_delegate_as_a_dependency(): void {
		$calls    = 0;
		$injector = ( new SimpleInjector() )
			->share( SharedLeaf::class )
			->delegate(
				SharedLeaf::class,
				static function () use ( &$calls ): SharedLeaf {
					++$calls;
					return new SharedLeaf();
				}
			);
		$object   = $injector->make( TwoOfTheSameDependency::class );

		$this->assertInstanceOf( TwoOfTheSameDependency::class, $object );
		$this->assertSame( $object->first, $object->second );
		$this->assertSame( $object->first, $injector->make( SharedLeaf::class ) );
		$this->assertSame( 1, $calls );
	}

	public function test_it_can_instantiate_a_class_with_named_arguments(): void {
		$object = ( new SimpleInjector() )
			->make(
				DummyClassWithNamedArguments::class,
				[
					'argument_a' => 42,
					'argument_b' => 'Mr Alderson',
				]
			);

		$this->assertInstanceOf( DummyClassWithNamedArguments::class, $object );
		$this->assertEquals( 42, $object->get_argument_a() );
		$this->assertEquals( 'Mr Alderson', $object->get_argument_b() );
	}

	public function test_it_allows_for_skipping_named_arguments_with_default_values(): void {
		$object = ( new SimpleInjector() )
			->make(
				DummyClassWithNamedArguments::class,
				[ 'argument_a' => 42 ]
			);

		$this->assertInstanceOf( DummyClassWithNamedArguments::class, $object );
		$this->assertEquals( 42, $object->get_argument_a() );
		$this->assertEquals( 'Mr Meeseeks', $object->get_argument_b() );
	}

	public function test_it_throws_if_a_required_named_arguments_is_missing(): void {
		$this->expectException( FailedToMakeInstance::class );

		( new SimpleInjector() )
			->make( DummyClassWithNamedArguments::class );
	}

	public function test_it_throws_if_a_circular_reference_is_detected(): void {
		$this->expectException( FailedToMakeInstance::class );
		$this->expectExceptionCode( FailedToMakeInstance::CIRCULAR_REFERENCE );

		( new SimpleInjector() )
			->bind(
				DummyClass::class,
				DummyClassWithDependency::class
			)
			->make( DummyClassWithDependency::class );
	}

	public function test_it_resolves_a_class_reached_by_two_branches_of_the_tree(): void {
		$object = ( new SimpleInjector() )->make( DiamondTop::class );

		$this->assertInstanceOf( DiamondTop::class, $object );
		$this->assertInstanceOf( SharedLeaf::class, $object->left->leaf );
		$this->assertInstanceOf( SharedLeaf::class, $object->right->leaf );
	}

	public function test_it_resolves_two_parameters_of_the_same_type(): void {
		$object = ( new SimpleInjector() )->make( TwoOfTheSameDependency::class );

		$this->assertInstanceOf( TwoOfTheSameDependency::class, $object );
		$this->assertInstanceOf( SharedLeaf::class, $object->first );
		$this->assertInstanceOf( SharedLeaf::class, $object->second );
	}

	public function test_it_names_the_resolution_path_of_a_circular_reference(): void {
		try {
			( new SimpleInjector() )->make( CycleStart::class );
			$this->fail( 'Resolving a class that depends on itself did not throw.' );
		} catch ( FailedToMakeInstance $exception ) {
			$this->assertSame( FailedToMakeInstance::CIRCULAR_REFERENCE, $exception->getCode() );
			$this->assertSame(
				\sprintf(
					'Circular reference detected while trying to resolve the interface or class "%1$s",'
						. ' along the resolution path %1$s -> %2$s -> %1$s.',
					CycleStart::class,
					CycleEnd::class
				),
				$exception->getMessage()
			);
		}
	}

	public function test_it_throws_if_bindings_form_a_cycle(): void {
		try {
			( new SimpleInjector() )
				->bind( BindingCycleFrom::class, BindingCycleTo::class )
				->bind( BindingCycleTo::class, BindingCycleFrom::class )
				->make( BindingCycleFrom::class );
			$this->fail( 'Resolving two interfaces bound to each other did not throw.' );
		} catch ( FailedToMakeInstance $exception ) {
			$this->assertSame( FailedToMakeInstance::CIRCULAR_REFERENCE, $exception->getCode() );
			$this->assertStringEndsWith(
				\sprintf(
					'%1$s -> %2$s -> %1$s.',
					BindingCycleFrom::class,
					BindingCycleTo::class
				),
				$exception->getMessage()
			);
		}
	}

	public function test_it_can_delegate_instantiation(): void {
		$made     = new DummyClassWithDependency( new DummyClass() );
		$received = null;
		$injector = ( new SimpleInjector() )
			->delegate(
				DummyInterface::class,
				function ( string $class_name ) use ( $made, &$received ): DummyInterface {
					$received = $class_name;
					return $made;
				}
			);
		$object   = $injector->make( DummyInterface::class );

		$this->assertSame( $made, $object );
		$this->assertEquals( DummyInterface::class, $received );
	}

	public function test_delegation_works_across_resolution(): void {
		$made     = new DummyClassWithDependency( new DummyClass() );
		$received = null;
		$injector = ( new SimpleInjector() )
			->bind(
				DummyInterface::class,
				DummyClassWithDependency::class
			)
			->delegate(
				DummyClassWithDependency::class,
				function ( string $class_name ) use ( $made, &$received ): DummyClassWithDependency {
					$received = $class_name;
					return $made;
				}
			);
		$object   = $injector->make( DummyInterface::class );

		$this->assertSame( $made, $object );
		$this->assertEquals( DummyClassWithDependency::class, $received );
	}

	/**
	 * @return array<string,array{0:mixed}> What a broken delegate returns.
	 */
	public static function data_invalid_delegate_results(): array {
		return [
			'object of another class' => [ new stdClass() ],
			'no object'               => [ 'not an object' ],
			'nothing'                 => [ null ],
		];
	}

	/**
	 * @dataProvider data_invalid_delegate_results
	 *
	 * @param mixed $result What the delegate returns.
	 */
	public function test_it_throws_if_a_delegate_returns_no_instance_of_the_class( $result ): void {
		$injector = ( new SimpleInjector() )
			->delegate( DummyInterface::class, static fn() => $result );

		$this->expectException( FailedToMakeInstance::class );
		$this->expectExceptionCode( FailedToMakeInstance::INVALID_DELEGATE );
		$type = is_object( $result ) ? get_class( $result ) : gettype( $result );

		$this->expectExceptionMessage(
			'The delegate for "' . DummyInterface::class . '" returned ' . $type . ', not an instance of that class.'
		);

		$injector->make( DummyInterface::class );
	}

	public function test_it_throws_if_a_delegate_for_a_dependency_returns_no_instance_of_the_class(): void {
		$injector = ( new SimpleInjector() )
			->delegate( DummyClass::class, static fn(): stdClass => new stdClass() );

		$this->expectException( FailedToMakeInstance::class );
		$this->expectExceptionCode( FailedToMakeInstance::INVALID_DELEGATE );
		$this->expectExceptionMessage(
			'The delegate for "' . DummyClass::class . '" returned stdClass, not an instance of that class.'
		);

		$injector->make( DummyClassWithDependency::class );
	}

	public function test_arguments_can_be_bound(): void {
		$object = ( new SimpleInjector() )
			->bind_argument(
				DummyClassWithNamedArguments::class,
				'argument_a',
				42
			)
			->bind_argument(
				SimpleInjector::GLOBAL_ARGUMENTS, // @phpstan-ignore argument.type (a sentinel, not a class-string)
				'argument_b',
				'Mr Alderson'
			)
			->make( DummyClassWithNamedArguments::class );

		$this->assertInstanceOf( DummyClassWithNamedArguments::class, $object );
		$this->assertEquals( 42, $object->get_argument_a() );
		$this->assertEquals( 'Mr Alderson', $object->get_argument_b() );
	}

	public function test_it_reports_invalid_reflection_without_swallowing_it(): void {
		// PHP accepts differently cased class names, but the injector requires the declared FQCN.
		$this->assertTrue( class_exists( DummyClass::class ) );
		$class_name = strtolower( DummyClass::class );

		try {
			( new SimpleInjector() )->make( $class_name );
			$this->fail( 'A class name that differs from its reflection did not throw.' );
		} catch ( FailedToMakeInstance $exception ) {
			$this->assertSame(
				'Could not create a reflection for the class "' . $class_name . '".',
				$exception->getMessage()
			);
			$this->assertSame( FailedToMakeInstance::INVALID_REFLECTION, $exception->getCode() );
		}
	}

	public function test_it_keeps_the_reflection_failure_as_previous(): void {
		// Deliberately violate the class-string contract to exercise the runtime boundary.
		/** @var class-string $missing_class */
		$missing_class = __NAMESPACE__ . '\\MissingClass';

		try {
			( new SimpleInjector() )->make( $missing_class );
			$this->fail( 'A missing class did not throw.' );
		} catch ( FailedToMakeInstance $exception ) {
			$this->assertSame( FailedToMakeInstance::UNREFLECTABLE_CLASS, $exception->getCode() );
			$this->assertInstanceOf( \ReflectionException::class, $exception->getPrevious() );
		}
	}

	public function test_it_keeps_the_default_value_reflection_failure_as_previous(): void {
		$failure   = new \ReflectionException( 'Default value could not be read.' );
		$parameter = $this->createMock( \ReflectionParameter::class );
		$parameter->method( 'getName' )->willReturn( 'argument' );
		$parameter->method( 'isDefaultValueAvailable' )->willThrowException( $failure );
		$method = new \ReflectionMethod( SimpleInjector::class, 'resolve_argument_by_name' );
		$method->setAccessible( true );

		try {
			$method->invoke( new SimpleInjector(), DummyClass::class, $parameter, [] );
			$this->fail( 'A failed default value lookup did not throw.' );
		} catch ( FailedToMakeInstance $exception ) {
			$this->assertSame( FailedToMakeInstance::UNRESOLVED_ARGUMENT, $exception->getCode() );
			$this->assertSame( $failure, $exception->getPrevious() );
		}
	}

	public function test_exception_constructors_still_accept_calls_without_a_cause(): void {
		$this->assertNull( FailedToMakeInstance::for_unreflectable_class( DummyClass::class )->getPrevious() );
		$this->assertNull(
			FailedToMakeInstance::for_unresolved_argument( 'argument', DummyClass::class )->getPrevious()
		);
	}

	/**
	 * @requires PHP >= 8.0
	 */
	public function test_it_builds_a_union_typed_parameter_from_a_bound_argument(): void {
		$class    = $this->declare_fixture(
			'UnionTypedParameter',
			'DummyClass|DummyInterface $dependency'
		);
		$argument = new DummyClass();

		$object = ( new SimpleInjector() )
			->bind_argument( $class, 'dependency', $argument )
			->make( $class );

		$this->assertInstanceOf( $class, $object );
		$this->assertSame( $argument, \get_object_vars( $object )['dependency'] );
	}

	/**
	 * @requires PHP >= 8.0
	 */
	public function test_it_falls_back_to_the_default_of_a_union_typed_parameter(): void {
		$class = $this->declare_fixture(
			'UnionTypedParameterWithDefault',
			'DummyClass|DummyInterface|null $dependency = null'
		);

		$object = ( new SimpleInjector() )->make( $class );

		$this->assertInstanceOf( $class, $object );
		$this->assertNull( \get_object_vars( $object )['dependency'] );
	}

	/**
	 * @requires PHP >= 8.0
	 */
	public function test_it_throws_for_an_unbound_union_typed_parameter(): void {
		$class = $this->declare_fixture(
			'UnboundUnionTypedParameter',
			'DummyClass|DummyInterface $dependency'
		);

		$this->expectException( FailedToMakeInstance::class );
		$this->expectExceptionCode( FailedToMakeInstance::UNRESOLVED_ARGUMENT );
		$this->expectExceptionMessage(
			'Could not resolve the argument "dependency" while trying to instantiate the class "' . $class . '".'
		);

		( new SimpleInjector() )->make( $class );
	}

	/**
	 * @requires PHP >= 8.1
	 */
	public function test_it_builds_an_intersection_typed_parameter_from_a_bound_argument(): void {
		$class    = $this->declare_fixture(
			'IntersectionTypedParameter',
			'\Countable&\IteratorAggregate $dependency'
		);
		$argument = new \ArrayObject();

		$object = ( new SimpleInjector() )
			->bind_argument( $class, 'dependency', $argument )
			->make( $class );

		$this->assertInstanceOf( $class, $object );
		$this->assertSame( $argument, \get_object_vars( $object )['dependency'] );
	}

	public function test_it_can_use_custom_instantiator(): void {
		/** @var MockObject&Instantiator $mock_instantiator */
		$mock_instantiator = $this->createMock( Instantiator::class );
		$mock_instantiator->expects( $this->once() )
			->method( 'instantiate' )
			->with( DummyClass::class, [] )
			->willReturn( new DummyClass() );

		$injector = new SimpleInjector( $mock_instantiator );
		$instance = $injector->make( DummyClass::class );

		$this->assertInstanceOf( DummyClass::class, $instance );
	}

	/**
	 * Declare a fixture class whose constructor takes one parameter.
	 *
	 * Union and intersection types are newer than PHP 7.4, the floor for
	 * tests/ as well, so a fixture file using them would not even parse on
	 * 7.4. The tests that need such a fixture require a newer PHP and declare
	 * it at runtime instead.
	 *
	 * @param string $name      Short name of the fixture class.
	 * @param string $parameter Constructor parameter, as written in PHP.
	 * @return class-string Fully qualified name of the fixture class.
	 */
	private function declare_fixture( string $name, string $parameter ): string {
		$namespace = 'MWPD\BasicScaffold\Tests\Fixture\Injector';
		$class     = $namespace . '\\' . $name;

		if ( ! \class_exists( $class, false ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- The code is a fixed string from this test, see above.
			eval(
				"namespace {$namespace};
				use MWPD\BasicScaffold\Tests\Fixture\DummyClass;
				use MWPD\BasicScaffold\Tests\Fixture\DummyInterface;
				final class {$name} {
					public \$dependency;
					public function __construct( {$parameter} ) {
						\$this->dependency = \$dependency;
					}
				}"
			);
		}

		/** @var class-string $class */
		return $class;
	}
}
