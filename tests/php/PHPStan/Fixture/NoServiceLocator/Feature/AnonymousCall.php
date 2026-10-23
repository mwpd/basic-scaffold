<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class AnonymousCall {
	public function create(): object {
		return new class {
			public function configure( \MWPD\BasicScaffold\Infrastructure\Injector $injector ): void {
				$injector->share( self::class );
			}
		};
	}
}
