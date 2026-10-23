<?php

declare( strict_types=1 );

namespace Unrelated;

final class Outside {
	public function configure( \MWPD\BasicScaffold\Infrastructure\ServiceContainer $container, \MWPD\BasicScaffold\Infrastructure\Injector $injector ): void {
		$injector->bind( self::class, self::class );
	}
}
