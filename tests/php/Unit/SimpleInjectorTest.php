<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Tests\Fixture\DummyClass;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithDependency;
use MWPD\BasicScaffold\Tests\Fixture\DummyInterface;
use MWPD\BasicScaffold\Tests\Fixture\DummyClassWithNamedArguments;
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

	public function test_it_can_delegate_instantiation(): void {
		$injector = ( new SimpleInjector() )
			->delegate(
				DummyInterface::class,
				function ( string $class_name ): stdClass {
					$object             = new stdClass();
					$object->class_name = $class_name;
					return $object;
				}
			);
		$object   = $injector->make( DummyInterface::class );

		$this->assertInstanceOf( stdClass::class, $object );
		$this->assertObjectHasProperty( 'class_name', $object );
		$this->assertEquals( DummyInterface::class, $object->class_name );
	}

	public function test_delegation_works_across_resolution(): void {
		$injector = ( new SimpleInjector() )
			->bind(
				DummyInterface::class,
				DummyClassWithDependency::class
			)
			->delegate(
				DummyClassWithDependency::class,
				function ( string $class_name ): stdClass {
					$object             = new stdClass();
					$object->class_name = $class_name;
					return $object;
				}
			);
		$object   = $injector->make( DummyInterface::class );

		$this->assertInstanceOf( stdClass::class, $object );
		$this->assertObjectHasProperty( 'class_name', $object );
		$this->assertEquals( DummyClassWithDependency::class, $object->class_name );
	}

	public function test_arguments_can_be_bound(): void {
		$object = ( new SimpleInjector() )
			->bind_argument(
				DummyClassWithNamedArguments::class,
				'argument_a',
				42
			)
			->bind_argument(
				SimpleInjector::GLOBAL_ARGUMENTS, // @phpstan-ignore-line
				'argument_b',
				'Mr Alderson'
			)
			->make( DummyClassWithNamedArguments::class );

		$this->assertInstanceOf( DummyClassWithNamedArguments::class, $object );
		$this->assertEquals( 42, $object->get_argument_a() );
		$this->assertEquals( 'Mr Alderson', $object->get_argument_b() );
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
}
