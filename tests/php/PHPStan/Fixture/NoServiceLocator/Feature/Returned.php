<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Returned {
	public function container(): \MWPD\BasicScaffold\Infrastructure\ServiceContainer {}
}
