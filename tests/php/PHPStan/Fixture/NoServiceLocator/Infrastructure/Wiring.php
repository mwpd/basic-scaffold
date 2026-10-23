<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Infrastructure;

final class Wiring {
	public function configure( \MWPD\BasicScaffold\Infrastructure\ServiceContainer $container, \MWPD\BasicScaffold\Infrastructure\Injector $injector ): void {
		$injector->bind( self::class, self::class );
	}
}
