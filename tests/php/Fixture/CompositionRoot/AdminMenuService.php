<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class AdminMenuService implements Service, Delayed, Registerable {

	public static function get_registration_action(): string {
		return 'admin_menu';
	}

	public function register(): void {
	}
}
