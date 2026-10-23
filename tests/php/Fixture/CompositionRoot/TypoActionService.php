<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class TypoActionService implements Service, Delayed, Registerable {

	use DefaultRegistrationPriority;

	public static function get_registration_action(): string {
		return 'wp_laoded';
	}

	public function register(): void {
	}
}
