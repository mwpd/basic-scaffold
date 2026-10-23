<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Service;

final class EmptyDelayedService implements Service, Delayed {

	use DefaultRegistrationPriority;

	public static function get_registration_action(): string {
		return ' ';
	}
}
