<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\ConstructorsOnlyAssign\Excluded;

use MWPD\BasicScaffold\Infrastructure\Service as ServiceMarker;

final class Service implements ServiceMarker {

	public function __construct() {
		get_option( 'mwpd_basic_scaffold_option' );
	}
}
