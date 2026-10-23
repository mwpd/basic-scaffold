<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\InvalidService;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\LazilyInstantiatedService;
use stdClass;

final class LazilyInstantiatedServiceTest extends TestCase {

	public function test_it_defers_the_callable_until_instantiation(): void {
		$service = $this->createMock( Service::class );
		$calls   = 0;
		$lazy    = new LazilyInstantiatedService(
			static function () use ( $service, &$calls ): Service {
				++$calls;
				return $service;
			}
		);

		$this->assertSame( 0, $calls );
		$this->assertSame( $service, $lazy->instantiate() );
		$this->assertSame( 1, $calls );
	}

	/**
	 * @dataProvider invalid_service_results
	 * @param mixed $result Invalid result returned by the callable.
	 */
	public function test_it_throws_when_the_callable_does_not_return_a_service( $result ): void {
		$lazy = new LazilyInstantiatedService( static fn () => $result );

		$this->expectException( InvalidService::class );
		$this->expectExceptionMessage( InvalidService::from_lazy_service( $result )->getMessage() );

		$lazy->instantiate();
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_service_results(): array {
		return [
			'ordinary object' => [ new stdClass() ],
			'string'          => [ 'invalid' ],
			'null'            => [ null ],
			'false'           => [ false ],
			'array'           => [ [] ],
		];
	}
}
