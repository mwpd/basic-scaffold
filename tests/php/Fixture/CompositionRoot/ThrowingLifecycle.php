<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class ThrowingLifecycle implements Service, Registerable, Activateable, Deactivateable {

	public function register(): void {
		throw new \RuntimeException( 'registration failed' );
	}

	private bool $activated = false;

	public function activate(): void {
		if ( $this->activated ) {
			throw new \RuntimeException( 'activation failed' );
		}

		$this->activated = true;
	}

	public function deactivate(): void {
		throw new \RuntimeException( 'deactivation failed' );
	}
}
