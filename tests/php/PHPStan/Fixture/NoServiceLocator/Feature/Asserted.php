<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Asserted {
	/** @phpstan-assert \MWPD\BasicScaffold\Infrastructure\ServiceContainer $value */
	public function check( $value ): void {}
}
