<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Reconfigures implements \MWPD\BasicScaffold\Infrastructure\Service {
	public function configure( \MWPD\BasicScaffold\Infrastructure\Injector $injector ): void {
		$injector->bind( self::class, self::class );
		$injector->share( self::class );
		$injector->delegate( self::class, static function () {} );
		$injector->bind_argument( self::class, 'key', 'value' );
	}
}
