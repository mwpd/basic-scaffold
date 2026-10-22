<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature\Service;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Concrete;

final class Plugin {

	public function service(): Service {
		return new Service( new Concrete() );
	}
}
