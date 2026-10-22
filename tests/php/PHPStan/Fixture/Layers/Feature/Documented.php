<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature;

/**
 * @method \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Plugin plugin()
 */
final class Documented {

	public function work( object $thing ): void {
		/** @var \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Concrete $thing */
		$thing->work();
	}
}
