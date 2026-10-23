<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class IdenticalRegistration implements Service, Registerable {

	public function register(): void {
		add_action( 'init', [ $this, 'run' ], 10, 0 );
		add_filter( 'the_content', [ $this, 'filter' ] );
	}

	public function run(): void {
	}

	public function filter( string $content ): string {
		return $content;
	}
}
