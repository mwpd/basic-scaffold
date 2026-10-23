<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class AppendingActivation implements Service, Activateable {

	public function activate(): void {
		add_post_meta( 42, 'fixture_runs', 'activated' );
		add_user_meta( 42, 'fixture_runs', 'activated' );
		add_term_meta( 42, 'fixture_runs', 'activated' );
		add_comment_meta( 42, 'fixture_runs', 'activated' );
	}
}
