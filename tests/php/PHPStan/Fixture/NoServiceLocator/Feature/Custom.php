<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Custom {
	public function configure( \stdClass $container, \ArrayObject $injector, \MWPD\BasicScaffold\Infrastructure\Injector $default ): void {
		$injector->append( 'value' );
		$default->bind( self::class, self::class );
	}
}
