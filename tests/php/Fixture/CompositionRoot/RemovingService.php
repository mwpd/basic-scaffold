<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Service;

final class RemovingService implements Service {

	public function __construct() {
		remove_filter( 'the_content', 'wpautop' );
	}
}
