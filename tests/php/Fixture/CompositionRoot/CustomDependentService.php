<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

final class CustomDependentService implements Service, Delayed, HasDependencies {

	public static function get_registration_action(): string {
		return 'fixture.custom_action';
	}

	public static function get_dependencies(): array {
		return [ 'b' ];
	}
}
