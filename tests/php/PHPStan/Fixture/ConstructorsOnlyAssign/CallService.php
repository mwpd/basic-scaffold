<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\ConstructorsOnlyAssign;

use MWPD\BasicScaffold\Infrastructure\Service;

final class CallService implements Service {

	public function __construct() {
		$this->option = get_option( 'mwpd_basic_scaffold_option' );
	}
}
