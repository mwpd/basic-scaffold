<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure;

trait LeakyTrait {

	public function service(): object {
		return new \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature\Service( new Concrete() );
	}
}
