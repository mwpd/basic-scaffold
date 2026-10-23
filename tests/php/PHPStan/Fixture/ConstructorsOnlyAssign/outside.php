<?php

declare( strict_types=1 );

namespace OtherPlugin;

use MWPD\BasicScaffold\Infrastructure\Service;

final class OutsideService implements Service {

	public function __construct() {
		get_option( 'mwpd_basic_scaffold_option' );
	}
}
