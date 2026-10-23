<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\ConstructorsOnlyAssign;

final class NotService {

	public function __construct() {
		get_option( 'mwpd_basic_scaffold_option' );
	}
}
