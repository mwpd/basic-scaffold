<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\Service;

final class RemovingDeactivation implements Service, Deactivateable {

	public function deactivate(): void {
		remove_filter( 'the_content', '__return_true' );
	}
}
