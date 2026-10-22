<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Exception\LayerException;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature\Service;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Plugin;

final class LeakyInfrastructure implements Contract {

	public function wire( Plugin $plugin ): Service {
		if ( ! $plugin instanceof Plugin ) {
			throw LayerException::for_reason();
		}

		return new Service( new Concrete() );
	}

	public function work(): void {
	}
}
