<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure;

final class Concrete implements Contract, Marker {

	public function work(): void {
	}
}
