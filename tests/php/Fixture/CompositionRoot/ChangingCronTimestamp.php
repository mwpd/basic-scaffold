<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class ChangingCronTimestamp implements Service, Activateable {

	private int $runs = 0;

	public function activate(): void {
		// A counter exposes duplicate scheduling without depending on the clock.
		wp_schedule_event( ++$this->runs, 'daily', 'fixture_event' );
	}
}
