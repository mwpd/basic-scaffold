<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\ConstructorsOnlyAssign;

use MWPD\BasicScaffold\Infrastructure\Injector;

abstract class BootstrapInjector implements Injector {

	public function __construct() {
		get_option( 'mwpd_basic_scaffold_option' );
	}
}
