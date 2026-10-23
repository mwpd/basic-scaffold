<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\InvalidService;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\LazilyInstantiatedService;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\SimpleServiceContainer;

final class SimpleServiceContainerTest extends TestCase {

	public function test_it_reports_whether_an_identifier_is_stored(): void {
		$container = new SimpleServiceContainer();
		$service   = $this->createMock( Service::class );

		$this->assertFalse( $container->has( 'sample' ) );
		$container->put( 'sample', $service );
		$this->assertTrue( $container->has( 'sample' ) );
		$this->assertFalse( $container->has( 'unknown' ) );
	}

	public function test_it_returns_the_service_stored_under_the_identifier(): void {
		$container = new SimpleServiceContainer();
		$first     = $this->createMock( Service::class );
		$second    = $this->createMock( Service::class );
		$container->put( 'first', $first );
		$container->put( 'second', $second );

		$this->assertSame( $first, $container->get( 'first' ) );
		$this->assertSame( $second, $container->get( 'second' ) );
	}

	public function test_it_replaces_a_service_under_the_same_identifier(): void {
		$container   = new SimpleServiceContainer();
		$original    = $this->createMock( Service::class );
		$replacement = $this->createMock( Service::class );
		$container->put( 'sample', $original );
		$container->put( 'sample', $replacement );

		$this->assertSame( $replacement, $container->get( 'sample' ) );
	}

	public function test_it_throws_for_an_unknown_identifier(): void {
		$this->expectException( InvalidService::class );
		$this->expectExceptionMessage( InvalidService::from_service_id( 'unknown' )->getMessage() );

		( new SimpleServiceContainer() )->get( 'unknown' );
	}

	public function test_it_unwraps_and_caches_a_lazy_service_only_when_retrieved(): void {
		$container = new SimpleServiceContainer();
		$service   = $this->createMock( Service::class );
		$calls     = 0;
		$lazy      = new LazilyInstantiatedService(
			static function () use ( $service, &$calls ): Service {
				++$calls;
				return $service;
			}
		);

		$container->put( 'lazy', $lazy );
		$this->assertTrue( $container->has( 'lazy' ) );
		$this->assertSame( 0, $calls );
		// Retrieve twice before asserting. Otherwise PHPStan assumes the second
		// call returns what the first assertion proved, and calls it redundant.
		$first  = $container->get( 'lazy' );
		$second = $container->get( 'lazy' );
		$this->assertSame( $service, $first );
		$this->assertSame( $service, $second );
		$this->assertSame( 1, $calls );
		$this->assertSame( $service, $container->offsetGet( 'lazy' ) );
	}

	public function test_it_rejects_an_invalid_lazy_service_on_retrieval(): void {
		$container = new SimpleServiceContainer();
		$container->put( 'lazy', new LazilyInstantiatedService( static fn (): string => 'invalid' ) );

		$this->expectException( InvalidService::class );
		$this->expectExceptionMessage( InvalidService::from_lazy_service( 'invalid' )->getMessage() );

		$container->get( 'lazy' );
	}
}
