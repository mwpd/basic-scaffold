<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Registration;

use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that is registered at once.
 */
final class RegisteredService implements Service, Registerable {

	public function register(): void {
		\add_action( 'wp_footer', [ $this, 'noop' ], 10, 0 );
	}

	public function noop(): void {}
}
