<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class DocumentedThrows {
	/** @throws \MWPD\BasicScaffold\Infrastructure\ServiceContainer */
	public function fail(): void {}
}
