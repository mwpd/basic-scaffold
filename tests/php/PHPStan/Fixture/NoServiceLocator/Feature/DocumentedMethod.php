<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class DocumentedMethod {
	/** @param \MWPD\BasicScaffold\Infrastructure\ServiceContainer $container */
	public function consume( $container ): void {}
}
