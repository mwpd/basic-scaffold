<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Service;

use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that receives the injector to make objects on demand.
 */
final class TestServiceNeedingInjector implements Service {

	private Injector $injector;

	public function __construct( Injector $injector ) {
		$this->injector = $injector;
	}

	public function get_injector(): Injector {
		return $this->injector;
	}
}
