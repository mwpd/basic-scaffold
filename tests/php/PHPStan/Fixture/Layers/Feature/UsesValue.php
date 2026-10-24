<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Amount;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Concrete;

final class UsesValue {

	public function work( Concrete $concrete ): Amount {
		return Amount::of( 1 );
	}
}
