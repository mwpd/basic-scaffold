<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature\Service;

final class Stray {

	public function service( Service $service ): Service {
		return $service;
	}
}
