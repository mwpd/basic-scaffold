<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class ChangingDeactivation implements Service, Deactivateable {

	private int $runs = 0;

	public function deactivate(): void {
		update_option( 'fixture_runs', ++$this->runs );
	}
}
