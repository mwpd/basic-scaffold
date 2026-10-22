<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure;

final class InvokesClosure {

	public function run( object $service ): void {
		( static function ( \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature\Service $service ): void {
		} )( $service );
	}
}
