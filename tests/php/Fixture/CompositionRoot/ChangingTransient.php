<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class ChangingTransient implements Service, Activateable {

	private int $runs = 0;

	public function activate(): void {
		set_transient( 'fixture_runs', ++$this->runs );
		update_user_meta( 42, 'fixture_runs', $this->runs );
	}
}
