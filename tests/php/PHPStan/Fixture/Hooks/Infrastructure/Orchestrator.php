<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure;

final class Orchestrator {

	public function __construct() {
		add_action( 'init', [ $this, 'boot' ] );
	}

	public function boot(): void {
		add_action( 'wp', [ $this, 'boot' ] );
	}
}
