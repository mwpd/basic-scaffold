<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class ChangingPriorityRegistration implements Service, Registerable {

	private int $priority = 10;

	public function register(): void {
		add_action( 'init', [ $this, 'run' ], $this->priority++, 0 );
	}

	public function run(): void {
	}
}
