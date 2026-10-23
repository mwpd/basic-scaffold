<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class GuardedCronActivation implements Service, Activateable {

	public function activate(): void {
		if ( wp_next_scheduled( 'fixture_event', [ 42 ] ) === false ) {
			wp_schedule_event( 100, 'daily', 'fixture_event', [ 42 ] );
		}
	}
}
