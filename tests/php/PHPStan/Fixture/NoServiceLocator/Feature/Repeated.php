<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

use MWPD\BasicScaffold\Infrastructure\ServiceContainer;

final class Repeated {
	/** @var ServiceContainer */
	private ServiceContainer $container;
	public function __construct( ServiceContainer $container ) {
		$this->container = $container;
	}
}
