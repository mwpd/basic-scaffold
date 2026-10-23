<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Creates implements \MWPD\BasicScaffold\Infrastructure\Service {
	private \MWPD\BasicScaffold\Infrastructure\Injector $injector;
	public function __construct( \MWPD\BasicScaffold\Infrastructure\Injector $injector ) {
		$this->injector = $injector;
	}
	public function create(): object {
		return $this->injector->MaKe( self::class );
	}
}
