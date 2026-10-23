<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Constructor implements \MWPD\BasicScaffold\Infrastructure\Service {
	public function __construct( \MWPD\BasicScaffold\Infrastructure\ServiceContainer $container ) {}
}
