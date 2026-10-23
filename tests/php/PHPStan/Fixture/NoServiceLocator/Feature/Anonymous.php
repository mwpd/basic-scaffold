<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Anonymous {
	public function create(): object {
		return new class {
			private \MWPD\BasicScaffold\Infrastructure\ServiceContainer $container;
		};
	}
}
